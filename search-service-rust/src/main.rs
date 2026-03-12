use axum::{
    routing::get,
    Router,
    Json,
};
use serde::Serialize;

#[derive(Serialize)]
struct Product {
    id: u32,
    name: String,
    price: u32,
}

async fn search() -> Json<Vec<Product>> {
    let products = vec![
        Product { id: 1, name: "Gaming Laptop".to_string(), price: 1500 },
        Product { id: 2, name: "Mechanical Keyboard".to_string(), price: 120 },
    ];

    Json(products)
}

#[tokio::main]
async fn main() {
    let app = Router::new()
        .route("/search", get(search));

    let listener = tokio::net::TcpListener::bind("0.0.0.0:4000")
        .await
        .unwrap();

    println!("Search service running on http://localhost:4000");

    axum::serve(listener, app)
        .await
        .unwrap();
}