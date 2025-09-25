# Copilot Instructions for Semsary-backend

## Project Overview
- This is a Laravel-based backend for a property management platform. Major components include:
  - `app/Models/`: Eloquent models for core entities (e.g., Booking, Chat, Checkout)
  - `app/Http/Controllers/`: API endpoints and request handling
  - `app/Services/`: Business logic and integrations
  - `app/Jobs/`: Queueable background jobs (e.g., escrow release, OTP sending)
  - `app/Events/` & `app/Listeners/`: Event-driven architecture for notifications and workflow triggers
  - `routes/`: API, web, console, and channel route definitions
  - `config/`: Environment-specific and service configuration

## Developer Workflows
- **Run the app locally:**
  - Use `php artisan serve` to start the development server
- **Database migrations:**
  - Run `php artisan migrate` to apply migrations
- **Seeding data:**
  - Use `php artisan db:seed` for initial data
- **Testing:**
  - Run `php artisan test` or `vendor/bin/phpunit`
- **Queue workers:**
  - Start with `php artisan queue:work`

## Project-Specific Conventions
- **Service Layer:** Business logic is separated into `app/Services/`. Avoid placing logic in controllers.
- **Event-Driven Patterns:** Use `Events` and `Listeners` for cross-component communication (e.g., notifications, property assignment).
- **Resource Responses:** API responses are formatted using `app/Http/Resources/` classes.
- **Enums:** Use `app/Enums/` for domain-specific constants (e.g., notification purposes).
- **Jobs:** Long-running or async tasks are implemented as `Jobs` and dispatched to queues.
- **Repositories:** Data access abstraction is handled in `app/Repositories/`.

## Integration Points
- **External Services:**
  - Payment gateways via `Interfaces/PaymentGatewayInterface.php`
  - Cloudinary for media storage (`config/cloudinary.php`)
  - Broadcasting (e.g., Pusher) via `config/broadcasting.php`
- **Authentication:**
  - Uses Laravel Passport and Sanctum (`config/passport.php`, `config/sanctum.php`)

## Examples
- To add a new API endpoint:
  1. Create a controller in `app/Http/Controllers/`
  2. Define the route in `routes/api.php`
  3. Use a Resource class for response formatting
- To add a new background job:
  1. Create a Job in `app/Jobs/`
  2. Dispatch from a Service or Controller
  3. Ensure queue worker is running

## Key Files & Directories
- `app/Models/`, `app/Services/`, `app/Jobs/`, `app/Events/`, `app/Listeners/`, `app/Http/Resources/`, `routes/`, `config/`

---

If any conventions or workflows are unclear, please ask for clarification or provide feedback to improve these instructions.
