class MakeSellerOptionalInProducts < ActiveRecord::Migration[8.1]
  def change
    change_column_null :products, :seller_id, true
  end
end