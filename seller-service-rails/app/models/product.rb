class Product < ApplicationRecord
  belongs_to :seller, optional: true
  has_one :inventory, dependent: :destroy
  has_many :reservation_items, dependent: :restrict_with_exception
end
