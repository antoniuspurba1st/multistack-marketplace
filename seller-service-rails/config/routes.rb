Rails.application.routes.draw do
  get "up" => "rails/health#show", as: :rails_health_check

  resources :products, only: [:index, :show, :create, :update, :destroy] do
    collection do
      post :reserve
      post :confirm
      post :release
    end
  end
end
