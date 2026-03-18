# This file is auto-generated from the current state of the database. Instead
# of editing this file, please use the migrations feature of Active Record to
# incrementally modify your database, and then regenerate this schema definition.
#
# This file is the source Rails uses to define your schema when running `bin/rails
# db:schema:load`. When creating a new database, `bin/rails db:schema:load` tends to
# be faster and is potentially less error prone than running all of your
# migrations from scratch. Old migrations may fail to apply correctly if those
# migrations use external dependencies or application code.
#
# It's strongly recommended that you check this file into your version control system.

ActiveRecord::Schema[8.1].define(version: 2026_03_18_100010) do
  create_table "inventories", force: :cascade do |t|
    t.integer "product_id", null: false
    t.integer "stock"
    t.datetime "created_at", null: false
    t.datetime "updated_at", null: false
    t.index ["product_id"], name: "index_inventories_on_product_id"
  end

  create_table "products", force: :cascade do |t|
    t.string "name"
    t.decimal "price"
    t.integer "seller_id"
    t.datetime "created_at", null: false
    t.datetime "updated_at", null: false
    t.index ["seller_id"], name: "index_products_on_seller_id"
  end

  create_table "reservation_items", force: :cascade do |t|
    t.string "reservation_id", null: false
    t.integer "product_id", null: false
    t.integer "quantity", null: false
    t.datetime "created_at", null: false
    t.datetime "updated_at", null: false
    t.index ["product_id"], name: "index_reservation_items_on_product_id"
    t.index ["reservation_id", "product_id"], name: "index_reservation_items_on_reservation_id_and_product_id", unique: true
  end

  create_table "reservations", id: :string, force: :cascade do |t|
    t.string "request_id", null: false
    t.string "status", default: "reserved", null: false
    t.datetime "created_at", null: false
    t.datetime "updated_at", null: false
    t.index ["request_id"], name: "index_reservations_on_request_id", unique: true
    t.index ["status"], name: "index_reservations_on_status"
  end

  create_table "sellers", force: :cascade do |t|
    t.string "name"
    t.string "email"
    t.datetime "created_at", null: false
    t.datetime "updated_at", null: false
  end

  add_foreign_key "inventories", "products"
  add_foreign_key "products", "sellers"
  add_foreign_key "reservation_items", "products"
  add_foreign_key "reservation_items", "reservations", column: "reservation_id", primary_key: "id"
end
