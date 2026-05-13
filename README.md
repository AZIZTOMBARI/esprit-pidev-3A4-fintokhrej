# FINTOKHREJ

Description
- Purpose: Web application (back-office and front) built with PHP/Symfony to manage the Fintokhrej platform.
- Contents: Symfony application code, database migrations, a FaceID microservice, front-end assets, and unit tests.

Key features
- CRUD and management for entities (offers, places, outings, users).
- Database migrations and version history (migrations).
- HTTP entrypoint and Twig templates (index.php, templates).
- FaceID microservice located in faceid_service.
- Unit tests in tests.

Requirements
- PHP 8.x compatible with the Symfony version used.
- Composer.
- MySQL / MariaDB (or other supported database).
- Node.js and npm/yarn for front-end asset building (optional).
- Python for faceid_service (see requirements.txt).

Quick installation
1. Clone the repository:
   git clone <REPO_URL>
2. Install PHP dependencies:
   composer install
3. (Optional) Install front-end dependencies:
   cd assets
   npm install
   cd ..
4. Configure environment variables:
   - Copy .env or create .env.local and set `DATABASE_URL`, API keys, etc.
5. Create the database and run migrations:
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
6. (Optional) Load fixtures if available:
   php bin/console doctrine:fixtures:load

Run locally
- Start the built-in PHP server:
  php -S 127.0.0.1:8000 -t public
  or use the Symfony CLI:
  symfony serve
- Open http://127.0.0.1:8000

Front-end development
- Build/watch assets (depending on project setup):
  cd assets
  npm run dev

FaceID service
- The microservice is in faceid_service.
- Install Python dependencies:
  pip install -r requirements.txt
- Start the FaceID service if required:
  python app.py
- Configure the service URL/keys in your Symfony environment variables.

Testing
- Run unit tests:
  php bin/phpunit
- See tests for existing test cases.

Deployment
- Set proper environment variables (`APP_ENV`, `DATABASE_URL`, etc.).
- Run migrations on the target environment.
- Build and publish assets.
- Serve the app with a production web server (Nginx/Apache) using public as the document root.

Good practices
- Follow PSR coding standards.
- Use Symfony console generators for entities, controllers, and forms.
- Commit migration files; avoid rewriting migration history in production.

Contributing
- Open an issue to discuss major changes.
- Create a feature branch per task: `feature/your-feature`.
- Submit clear, tested pull requests.

Important files
- composer.json — PHP dependencies.
- config — Symfony configuration.
- src — application source code.
- public — web entrypoint and public assets.
- migrations — database migration history.
- faceid_service — FaceID microservice.
- tests — unit tests.

