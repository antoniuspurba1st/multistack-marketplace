from django.http import JsonResponse

def recommend(request, user_id):

    recommendations = [
        {"id": 1, "name": "Gaming Laptop"},
        {"id": 2, "name": "Mechanical Keyboard"},
        {"id": 3, "name": "Wireless Mouse"}
    ]

    return JsonResponse({
        "user_id": user_id,
        "recommendations": recommendations
    })