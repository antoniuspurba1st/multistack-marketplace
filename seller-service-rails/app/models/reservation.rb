class Reservation < ApplicationRecord
  self.primary_key = :id

  enum :status, {
    reserved: "reserved",
    confirmed: "confirmed",
    released: "released"
  }, validate: true

  has_many :reservation_items, dependent: :destroy

  validates :request_id, presence: true, uniqueness: true

  before_validation :assign_id, on: :create

  private

  def assign_id
    self.id ||= SecureRandom.uuid
  end
end
