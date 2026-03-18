class CreateReservations < ActiveRecord::Migration[8.1]
  def change
    create_table :reservations, id: :string do |t|
      t.string :request_id, null: false
      t.string :status, null: false, default: "reserved"
      t.timestamps
    end

    add_index :reservations, :request_id, unique: true
    add_index :reservations, :status
  end
end
