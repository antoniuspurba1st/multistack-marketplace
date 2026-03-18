require "test_helper"

class ProductsControllerTest < ActionDispatch::IntegrationTest
  test "reserve soft locks stock and returns reservation" do
    product = Product.create!(name: "Keyboard", price: 99.99)
    inventory = Inventory.create!(product: product, stock: 7)

    post reserve_products_url, params: {
      request_id: "reserve-1",
      items: [
        {
          product_id: product.id,
          quantity: 2
        }
      ]
    }, as: :json

    assert_response :success

    body = JSON.parse(response.body)
    assert_equal "reserved", body["status"]
    assert_not_nil body["reservation_id"]
    assert_equal 5, inventory.reload.stock
    assert_equal 1, ReservationItem.count
  end

  test "reserve is idempotent for the same request id" do
    product = Product.create!(name: "Keyboard", price: 99.99)
    inventory = Inventory.create!(product: product, stock: 7)

    2.times do
      post reserve_products_url, params: {
        request_id: "reserve-duplicate",
        items: [
          {
            product_id: product.id,
            quantity: 2
          }
        ]
      }, as: :json

      assert_response :success
    end

    assert_equal 5, inventory.reload.stock
    assert_equal 1, Reservation.count
    assert_equal 1, ReservationItem.count
  end

  test "confirm finalizes reservation without restoring stock" do
    product = Product.create!(name: "Keyboard", price: 99.99)
    inventory = Inventory.create!(product: product, stock: 5)

    post reserve_products_url, params: {
      request_id: "reserve-confirm",
      items: [
        {
          product_id: product.id,
          quantity: 2
        }
      ]
    }, as: :json

    reservation_id = JSON.parse(response.body)["reservation_id"]

    post confirm_products_url, params: {
      reservation_id: reservation_id
    }, as: :json

    assert_response :success

    body = JSON.parse(response.body)
    assert_equal "confirmed", body["status"]
    assert_equal 3, inventory.reload.stock
    assert_equal "confirmed", Reservation.find(reservation_id).status
  end

  test "release restores reserved stock" do
    product = Product.create!(name: "Keyboard", price: 99.99)
    inventory = Inventory.create!(product: product, stock: 5)

    post reserve_products_url, params: {
      request_id: "reserve-release",
      items: [
        {
          product_id: product.id,
          quantity: 2
        }
      ]
    }, as: :json

    reservation_id = JSON.parse(response.body)["reservation_id"]

    post release_products_url, params: {
      reservation_id: reservation_id
    }, as: :json

    assert_response :success

    body = JSON.parse(response.body)
    assert_equal "released", body["status"]
    assert_equal 5, inventory.reload.stock
    assert_equal "released", Reservation.find(reservation_id).status
  end

  test "reserve returns unprocessable entity when stock is not enough" do
    product = Product.create!(name: "Keyboard", price: 99.99)
    inventory = Inventory.create!(product: product, stock: 1)

    post reserve_products_url, params: {
      request_id: "reserve-fail",
      items: [
        {
          product_id: product.id,
          quantity: 2
        }
      ]
    }, as: :json

    assert_response :unprocessable_entity

    body = JSON.parse(response.body)
    assert_equal "Stock not enough", body["message"]
    assert_equal 1, inventory.reload.stock
    assert_equal 0, Reservation.count
  end
end
