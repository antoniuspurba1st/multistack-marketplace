class ReservationItem < ApplicationRecord
  belongs_to :reservation, foreign_key: :reservation_id
  belongs_to :product

  validates :quantity, numericality: { greater_than: 0, only_integer: true }
end
