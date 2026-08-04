# Filipino Cookbook Client Application

## 1. Application Title

**Filipino Cookbook Client Application**

## 2. Application Description

The Filipino Cookbook Client Application is a separate driver/client program that consumes the secured Filipino Cookbook API developed by a classmate. The application retrieves cookbook data through HTTP API endpoints and displays the responses using readable interface elements instead of raw JSON.

The client allows users to view Filipino food records, search foods by name, find one food by ID, view food categories, view ingredients, and submit a new food record through a POST request.

This application is intended for students, instructors, and beginner developers who need a simple user interface for testing and presenting API data.

## 3. Technologies Used

| Technology | Purpose |
| --- | --- |
| PHP | Server-side client application and API requests |
| HTML | Page structure |
| CSS | User interface styling |
| Filipino Cookbook API | Data source |
| JSON | API response format |
| XAMPP / Apache | Local web server |
| Git and GitHub | Version control and repository hosting |

## 4. Installation Instructions

1. Clone this client repository into the XAMPP `htdocs` folder.

   ```bash
   git clone https://github.com/YOUR-USERNAME/filipino-cookbook-client-galang.git
   ```

2. Open the project folder.

   ```bash
   cd filipino-cookbook-client-galang
   ```

3. Copy the example configuration file.

   ```bash
   copy config.example.php config.php
   ```

4. Open `config.php` and configure the API settings.

   ```php
   return [
       'api_base_url' => 'http://localhost/filipino-cookbook-api-cuares-main/public',
       'api_token' => 'YOUR_ACCESS_TOKEN',
       'api_developer' => 'Cuares, John Mark Perez',
   ];
   ```

5. Start **Apache** and **MySQL** in XAMPP.

6. Make sure the Filipino Cookbook API is also installed and running.

7. Open the client application in a browser.

   ```text
   http://localhost/filipino-cookbook-client-galang/
   ```

If you keep the client folder inside the API project during local testing, open:

```text
http://localhost/filipino-cookbook-api-cuares-main/client/
```

## 5. API Endpoints Used

| Method | Endpoint | Description |
| --- | --- | --- |
| GET | `/api/foods` | Retrieves all Filipino food records and displays them as food cards. |
| GET | `/api/foods/{id}` | Retrieves one Filipino food record by ID and displays its details. |
| GET | `/api/foods/random` | Retrieves a random Filipino food record. |
| GET | `/api/categories` | Retrieves all food categories and displays them in a table. |
| GET | `/api/categories/{id}/foods` | Retrieves all foods that belong to a specific category. |
| GET | `/api/categories/counts` | Retrieves the number of foods in each category. |
| GET | `/api/foods/search/{name}` | Searches food records by name. |
| GET | `/api/ingredients` | Retrieves all ingredients and displays them in a table. |
| POST | `/api/foods` | Adds a new Filipino food record with category, origin, instructions, and ingredient IDs through the Add Food form. |

## 6. Screenshots

### Foods Display

![Successful API Data Display](screenshots/foods-success.png)

### Food Details Display

![Food Details Display](screenshots/food-details-success.png)

### Categories Display

![Categories Display](screenshots/categories-success.png)

### Ingredients Display

![Ingredients Display](screenshots/ingredients-success.png)

### Search Foods Display

![Search Foods Display](screenshots/search-foods-success.png)

### Random Food Display

![Random Food Display](screenshots/random-food-success.png)

### Foods by Category Display

![Foods by Category Display](screenshots/foods-by-category-success.png)

### Category Food Counts Display

![Category Food Counts Display](screenshots/category-counts-success.png)

### Add Food (POST) Display

![Add Food Success](screenshots/add-food-success.png)

Confirms a new food record (Bagnet) was successfully submitted through the Add Food form, returning a success response from the `POST /api/foods` endpoint.

## 7. API Source and Acknowledgment

This client application uses the Filipino Cookbook API developed by:

**Developer:** Cuares, John Mark Perez

**GitHub Repository:** https://github.com/cuaresjohnmark-ux/filipino-cookbook-api-cuares

The API is used for educational purposes with the permission of the developer.

