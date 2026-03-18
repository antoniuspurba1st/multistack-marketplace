class ProductsController < ApplicationController
  class StockNotEnoughError < StandardError; end

  class InvalidReservationStateError < StandardError; end

  before_action :set_product, only: [:show, :update, :destroy]

  rescue_from ActiveRecord::RecordNotFound, with: :render_not_found
  rescue_from StockNotEnoughError, InvalidReservationStateError, with: :render_unprocessable_entity
  rescue_from ActionController::ParameterMissing, with: :render_bad_request

  def index
    products = Product.includes(:inventory)
    products = products.where("name LIKE ?", "%#{params[:search]}%") if params[:search].present?

    render json: products.map { |product| serialize_product(product) }
  end

  def show
    render json: serialize_product(@product)
  end

  def create
    product = Product.new(product_params)

    if product.save
      product.create_inventory!(stock: params[:stock] || 0)
      render json: serialize_product(product.reload), status: :created
    else
      render json: { errors: product.errors.full_messages }, status: :unprocessable_entity
    end
  end

  def update
    if @product.update(product_params)
      if params.key?(:stock)
        inventory = @product.inventory || @product.build_inventory
        inventory.update!(stock: params[:stock])
      end

      render json: serialize_product(@product.reload)
    else
      render json: { errors: @product.errors.full_messages }, status: :unprocessable_entity
    end
  end

  def destroy
    @product.destroy
    head :no_content
  end

  def reserve
    request_id = params.require(:request_id).to_s
    existing_reservation = Reservation.includes(reservation_items: { product: :inventory }).find_by(request_id: request_id)

    if existing_reservation
      render json: serialize_reservation(existing_reservation)
      return
    end

    items = reservation_items_payload
    reservation = nil

    ActiveRecord::Base.transaction do
      locked_products = lock_products_for_reservation!(items)

      reservation = Reservation.create!(
        request_id: request_id,
        status: :reserved
      )

      items.each do |item|
        product = locked_products.fetch(item[:product_id])
        inventory = product.inventory

        if inventory.stock.to_i < item[:quantity]
          raise StockNotEnoughError, "Stock not enough"
        end

        inventory.update!(stock: inventory.stock.to_i - item[:quantity])
        reservation.reservation_items.create!(
          product_id: product.id,
          quantity: item[:quantity]
        )
      end
    end

    reservation = Reservation.includes(reservation_items: { product: :inventory }).find(reservation.id)

    Rails.logger.info({
      event: "stock_reserved",
      reservation_id: reservation.id,
      request_id: request_id,
      items: items
    })

    render json: serialize_reservation(reservation)
  rescue ActiveRecord::RecordNotUnique
    reservation = Reservation.includes(reservation_items: { product: :inventory }).find_by!(request_id: request_id)
    render json: serialize_reservation(reservation)
  end

  def confirm
    reservation = with_locked_reservation(params.require(:reservation_id)) do |locked_reservation|
      if locked_reservation.released?
        raise InvalidReservationStateError, "Reservation already released"
      end

      locked_reservation.update!(status: :confirmed) if locked_reservation.reserved?
      locked_reservation
    end

    Rails.logger.info({
      event: "stock_reservation_confirmed",
      reservation_id: reservation.id
    })

    render json: serialize_reservation_status(reservation)
  end

  def release
    reservation = with_locked_reservation(params.require(:reservation_id)) do |locked_reservation|
      if locked_reservation.confirmed?
        raise InvalidReservationStateError, "Reservation already confirmed"
      end

      if locked_reservation.reserved?
        inventories = Inventory.where(
          product_id: locked_reservation.reservation_items.pluck(:product_id)
        ).order(:product_id).lock.index_by(&:product_id)

        locked_reservation.reservation_items.order(:product_id).each do |item|
          inventory = inventories.fetch(item.product_id)
          inventory.update!(stock: inventory.stock.to_i + item.quantity)
        end

        locked_reservation.update!(status: :released)
      end

      locked_reservation
    end

    Rails.logger.info({
      event: "stock_reservation_released",
      reservation_id: reservation.id
    })

    render json: serialize_reservation_status(reservation)
  end

  private

  def set_product
    @product = Product.includes(:inventory).find(params[:id])
  end

  def product_params
    params.permit(:name, :price)
  end

  def reservation_items_payload
    items = Array(params[:items]).map do |item|
      raw_item = item.respond_to?(:to_unsafe_h) ? item.to_unsafe_h : item.to_h

      {
        product_id: raw_item["product_id"].to_i,
        quantity: raw_item["quantity"].to_i
      }
    end

    if items.empty? || items.any? { |item| item[:product_id] <= 0 || item[:quantity] <= 0 }
      raise ActionController::ParameterMissing, :items
    end

    items
      .group_by { |item| item[:product_id] }
      .map do |product_id, product_items|
        {
          product_id: product_id,
          quantity: product_items.sum { |product_item| product_item[:quantity] }
        }
      end
      .sort_by { |item| item[:product_id] }
  end

  def lock_products_for_reservation!(items)
    product_ids = items.map { |item| item[:product_id] }
    products = Product.includes(:inventory).where(id: product_ids).index_by(&:id)
    missing_product_id = product_ids.find { |product_id| products[product_id].nil? }

    raise ActiveRecord::RecordNotFound, "Product not found" if missing_product_id

    inventories = Inventory.where(product_id: product_ids).order(:product_id).lock.index_by(&:product_id)

    items.each do |item|
      raise ActiveRecord::RecordNotFound, "Inventory not found" if inventories[item[:product_id]].nil?
    end

    product_ids.index_with do |product_id|
      product = products.fetch(product_id)
      product.association(:inventory).target = inventories.fetch(product_id)
      product
    end
  end

  def with_locked_reservation(reservation_id)
    reservation = nil

    ActiveRecord::Base.transaction do
      reservation = Reservation.lock.includes(reservation_items: { product: :inventory }).find(reservation_id)
      reservation = yield reservation
    end

    Reservation.includes(reservation_items: { product: :inventory }).find(reservation.id)
  end

  def serialize_reservation(reservation)
    {
      reservation_id: reservation.id,
      status: reservation.status,
      products: reservation.reservation_items
        .includes(product: :inventory)
        .map { |item| serialize_product(item.product.reload) }
        .uniq { |product| product[:id] }
    }
  end

  def serialize_reservation_status(reservation)
    {
      reservation_id: reservation.id,
      status: reservation.status
    }
  end

  def serialize_product(product)
    {
      id: product.id,
      name: product.name,
      price: product.price.to_f,
      stock: product.inventory&.stock.to_i
    }
  end

  def render_not_found
    render json: { message: "Product not found" }, status: :not_found
  end

  def render_unprocessable_entity(exception)
    render json: { message: exception.message }, status: :unprocessable_entity
  end

  def render_bad_request(exception)
    render json: { message: exception.message }, status: :bad_request
  end
end
