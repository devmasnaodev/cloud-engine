# Cloud Engine - AI Coding Agent Guidelines

This is a **Laravel 12 + Inertia.js (React 19) + Tailwind 4** application using modern Laravel patterns and tooling.

## Project-Specific Architecture

### Development Environment
- **DDEV** is the local development environment (see `.ddev/config.yaml`)
- Run `composer run dev` to start all services concurrently: server, queue, logs (Pail), and Vite
- Run `composer run dev:ssr` for SSR mode (builds SSR first, runs Inertia SSR server)
- Xdebug can be toggled via VS Code tasks: `DDEV: Enable Xdebug` / `DDEV: Disable Xdebug`

> **⚠️ All CLI commands must be executed inside the DDEV container:**
> - PHP / Artisan: `ddev artisan <command>` or `ddev php <file>`
> - Composer: `ddev composer <command>`
> - Node / npm: `ddev exec npm <command>` (e.g. `ddev exec npm run lint`)
> - Pint: `ddev exec vendor/bin/pint --dirty`
> - Never run `php artisan`, `npm run`, or `composer` directly on the host — there is no DB connection outside DDEV.

> **⚠️ Never commit before the user confirms the feature works as expected.**
> After implementing a change, present the result to the user and wait for explicit confirmation before running `git commit`. Only run `git add` + `git commit` after the user approves.

### Route Organization Pattern
Routes are split across multiple files with clear responsibility boundaries:
- `routes/web.php` - App entrypoint and authenticated app routes (servers, sites, provisioning) and includes `routes/settings.php`
- `routes/settings.php` - All authenticated settings routes (profile, password, 2FA, appearance)
- Laravel Fortify handles authentication routes automatically (login, register, password reset)
- `/` redirects to `servers.index` when authenticated and to `login` when not authenticated; there is no dashboard route anymore

### Frontend Architecture (Inertia + React)
- **Pages**: `resources/js/pages/` - Top-level Inertia page components (e.g., `servers/index.tsx`, `servers/show.tsx`, `sites/index.tsx`, `settings/profile.tsx`)
- **Layouts**: `resources/js/layouts/` - Reusable layout wrappers (`app-layout.tsx`, `auth-layout.tsx`, `settings/`)
- **Components**: `resources/js/components/` - Business components AND `ui/` shadcn-style primitives
- **Path Alias**: `@/` maps to `resources/js/` (configured in `tsconfig.json` and Vite)
- **UI Components**: Built with Radix UI primitives + `class-variance-authority` (CVA) pattern (see `components/ui/button.tsx`)
- **Styling Utilities**: All components import `cn()` from `@/lib/utils` for class merging

### Wayfinder Integration
- Wayfinder auto-generates TypeScript route functions from Laravel controllers (in `resources/js/actions/` and `resources/js/routes/`)
- Import controller methods directly: `import { show, update } from '@/actions/App/Http/Controllers/Settings/ProfileController'`
- Use with Inertia forms: `<Form {...update.form()}>` - automatically sets action URL and method
- Vite plugin auto-regenerates on file changes (`@laravel/vite-plugin-wayfinder` in `vite.config.ts`)

### Validation & Request Handling
- All validation uses dedicated FormRequest classes (e.g., `app/Http/Requests/Settings/ProfileUpdateRequest.php`)
- Validation rules use array syntax: `['required', 'string', 'max:255']` (check existing FormRequests for consistency)

### Shared Inertia Data
- Global shared data configured in `app/Http/Middleware/HandleInertiaRequests.php`
- Current implementation shares: app name, inspirational quote, auth user, sidebar state
- TypeScript types defined in `resources/js/types/index.d.ts` (see `SharedData` interface)

### Component Patterns
- **shadcn/ui-style components**: Use CVA for variants, `cn()` for conditional classes, `Slot` from Radix for polymorphic components
- **App-specific components**: Located in `resources/js/components/` (e.g., `app-sidebar.tsx`, `nav-main.tsx`)
- Dark mode support required: Use `dark:` variant (custom variant defined in `resources/css/app.css`)

### Middleware & Authentication
- Laravel 12 middleware registered in `bootstrap/app.php` (no separate Kernel file)
- Custom middleware: `HandleAppearance` (appearance cookie), `HandleInertiaRequests` (Inertia shared data)
- Fortify handles authentication - views customized via `FortifyServiceProvider` (see `app/Providers/FortifyServiceProvider.php`)
- Two-factor authentication enabled with confirmation required (see `config/fortify.php`)

### Styling & Theme System
- Tailwind 4: Configuration via `@theme` directive in CSS, not `tailwind.config.js`
- Custom theme defined in `resources/css/app.css` with CSS variables for colors
- Spacing: Always use `gap-*` utilities for flex/grid spacing, never margins between siblings
- Custom dark mode variant: `@custom-variant dark (&:is(.dark *));` allows `.dark` class on root

### Testing Conventions
- Pest 4 tests in `tests/Feature/` (feature tests) and `tests/Unit/` (unit tests)
- All feature tests use `RefreshDatabase` (configured in `tests/Pest.php`)
- Test factories in `database/factories/UserFactory.php` - always use factories for model creation in tests
- Run tests with filters inside DDEV: `ddev artisan test --filter=testName`

### Code Quality & Formatting
 - **Pint**: Run `ddev exec vendor/bin/pint --dirty` before committing (formats only changed files)
- **ESLint**: Run `ddev exec npm run lint` to auto-fix JS/TS issues
- **Prettier**: Run `ddev exec npm run format` to format frontend code (includes Tailwind class sorting)
- **TypeScript**: Run `ddev exec npm run types` to check types without emitting files

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.30
- inertiajs/inertia-laravel (INERTIA) - v2
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA) - v2
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `ddev artisan test` with a specific filename or filter.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v2 rules ===

## Inertia v2

- Make use of all Inertia features from v1 & v2. Check the documentation before making any changes to ensure we are taking the correct approach.

### Inertia v2 New Features
- Polling
- Prefetching
- Deferred props
- Infinite scrolling using merging props and `WhenVisible`
- Lazy loading data on scroll

### Deferred Props & Empty States
- When using deferred props on the frontend, you should add a nice empty state with pulsing / animated skeleton.

### Inertia Form General Guidance
- The recommended way to build forms when using Inertia is with the `<Form>` component - a useful example is below. Use `search-docs` with a query of `form component` for guidance.
- Forms can also be built using the `useForm` helper for more programmatic control, or to follow existing conventions. Use `search-docs` with a query of `useForm helper` for guidance.
- `resetOnError`, `resetOnSuccess`, and `setDefaultsOnSuccess` are available on the `<Form>` component. Use `search-docs` with a query of 'form component resetting' for guidance.


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `ddev artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `ddev artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `ddev artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `ddev artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- No middleware files in `app/Http/Middleware/`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- **No app\Console\Kernel.php** - use `bootstrap/app.php` or `routes/console.php` for console configuration.
- **Commands auto-register** - files in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== wayfinder/core rules ===

## Laravel Wayfinder

Wayfinder generates TypeScript functions and types for Laravel controllers and routes which you can import into your client side code. It provides type safety and automatic synchronization between backend routes and frontend code.

### Development Guidelines
- Always use `search-docs` to check wayfinder correct usage before implementing any features.
- Always Prefer named imports for tree-shaking (e.g., `import { show } from '@/actions/...'`)
- Avoid default controller imports (prevents tree-shaking)
- Run `ddev artisan wayfinder:generate` after route changes if Vite plugin isn't installed

### Feature Overview
- Form Support: Use `.form()` with `--with-form` flag for HTML form attributes — `<form {...store.form()}>` → `action="/posts" method="post"`
- HTTP Methods: Call `.get()`, `.post()`, `.patch()`, `.put()`, `.delete()` for specific methods — `show.head(1)` → `{ url: "/posts/1", method: "head" }`
- Invokable Controllers: Import and invoke directly as functions. For example, `import StorePost from '@/actions/.../StorePostController'; StorePost()`
- Named Routes: Import from `@/routes/` for non-controller routes. For example, `import { show } from '@/routes/post'; show(1)` for route name `post.show`
- Parameter Binding: Detects route keys (e.g., `{post:slug}`) and accepts matching object properties — `show("my-post")` or `show({ slug: "my-post" })`
- Query Merging: Use `mergeQuery` to merge with `window.location.search`, set values to `null` to remove — `show(1, { mergeQuery: { page: 2, sort: null } })`
- Query Parameters: Pass `{ query: {...} }` in options to append params — `show(1, { query: { page: 1 } })` → `"/posts/1?page=1"`
- Route Objects: Functions return `{ url, method }` shaped objects — `show(1)` → `{ url: "/posts/1", method: "get" }`
- URL Extraction: Use `.url()` to get URL string — `show.url(1)` → `"/posts/1"`

### Example Usage

<code-snippet name="Wayfinder Basic Usage" lang="typescript">
    // Import controller methods (tree-shakable)
    import { show, store, update } from '@/actions/App/Http/Controllers/PostController'

    // Get route object with URL and method...
    show(1) // { url: "/posts/1", method: "get" }

    // Get just the URL...
    show.url(1) // "/posts/1"

    // Use specific HTTP methods...
    show.get(1) // { url: "/posts/1", method: "get" }
    show.head(1) // { url: "/posts/1", method: "head" }

    // Import named routes...
    import { show as postShow } from '@/routes/post' // For route name 'post.show'
    postShow(1) // { url: "/posts/1", method: "get" }
</code-snippet>


### Wayfinder + Inertia
If your application uses the `<Form>` component from Inertia, you can use Wayfinder to generate form action and method automatically.
<code-snippet name="Wayfinder Form Component (React)" lang="typescript">

<Form {...store.form()}><input name="title" /></Form>

</code-snippet>


=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `ddev exec vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.


=== pest/core rules ===

## Pest
### Testing
- If you need to verify a feature is working, write or update a Unit / Feature test.

### Pest Tests
- All tests must be written using Pest. Use `ddev artisan make:test --pest {name}`.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files - these are core to the application.
- Tests should test all of the happy paths, failure paths, and weird paths.
- Tests live in the `tests/Feature` and `tests/Unit` directories.
- Pest tests look and behave like this:
<code-snippet name="Basic Pest Test Example" lang="php">
it('is true', function () {
    expect(true)->toBeTrue();
});
</code-snippet>

### Running Tests
- Run the minimal number of tests using an appropriate filter before finalizing code edits.
 - To run all tests: `ddev artisan test`.
 - To run all tests in a file: `ddev artisan test tests/Feature/ExampleTest.php`.
 - To filter on a particular test name: `ddev artisan test --filter=testName` (recommended after making a change to a related file).
- When the tests relating to your changes are passing, ask the user if they would like to run the entire test suite to ensure everything is still passing.

### Pest Assertions
- When asserting status codes on a response, use the specific method like `assertForbidden` and `assertNotFound` instead of using `assertStatus(403)` or similar, e.g.:
<code-snippet name="Pest Example Asserting postJson Response" lang="php">
it('returns all', function () {
    $response = $this->postJson('/api/docs', []);

    $response->assertSuccessful();
});
</code-snippet>

### Mocking
- Mocking can be very helpful when appropriate.
- When mocking, you can use the `Pest\Laravel\mock` Pest function, but always import it via `use function Pest\Laravel\mock;` before using it. Alternatively, you can use `$this->mock()` if existing tests do.
- You can also create partial mocks using the same import or self method.

### Datasets
- Use datasets in Pest to simplify tests which have a lot of duplicated data. This is often the case when testing validation rules, so consider going with this solution when writing tests for validation rules.

<code-snippet name="Pest Dataset Example" lang="php">
it('has emails', function (string $email) {
    expect($email)->not->toBeEmpty();
})->with([
    'james' => 'james@laravel.com',
    'taylor' => 'taylor@laravel.com',
]);
</code-snippet>


=== pest/v4 rules ===

## Pest 4

- Pest v4 is a huge upgrade to Pest and offers: browser testing, smoke testing, visual regression testing, test sharding, and faster type coverage.
- Browser testing is incredibly powerful and useful for this project.
- Browser tests should live in `tests/Browser/`.
- Use the `search-docs` tool for detailed guidance on utilizing these features.

### Browser Testing
- You can use Laravel features like `Event::fake()`, `assertAuthenticated()`, and model factories within Pest v4 browser tests, as well as `RefreshDatabase` (when needed) to ensure a clean state for each test.
- Interact with the page (click, type, scroll, select, submit, drag-and-drop, touch gestures, etc.) when appropriate to complete the test.
- If requested, test on multiple browsers (Chrome, Firefox, Safari).
- If requested, test on different devices and viewports (like iPhone 14 Pro, tablets, or custom breakpoints).
- Switch color schemes (light/dark mode) when appropriate.
- Take screenshots or pause tests for debugging when appropriate.

### Example Tests

<code-snippet name="Pest Browser Test Example" lang="php">
it('may reset the password', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $page = visit('/sign-in'); // Visit on a real browser...

    $page->assertSee('Sign In')
        ->assertNoJavascriptErrors() // or ->assertNoConsoleLogs()
        ->click('Forgot Password?')
        ->fill('email', 'nuno@laravel.com')
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!')

    Notification::assertSent(ResetPassword::class);
});
</code-snippet>

<code-snippet name="Pest Smoke Testing Example" lang="php">
$pages = visit(['/', '/about', '/contact']);

$pages->assertNoJavascriptErrors()->assertNoConsoleLogs();
</code-snippet>


=== inertia-react/core rules ===

## Inertia + React

- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="react">

import { Link } from '@inertiajs/react'
<Link href="/">Home</Link>

</code-snippet>


=== inertia-react/v2/forms rules ===

## Inertia + React Forms

<code-snippet name="`<Form>` Component Example" lang="react">

import { Form } from '@inertiajs/react'

export default () => (
    <Form action="/users" method="post">
        {({
            errors,
            hasErrors,
            processing,
            wasSuccessful,
            recentlySuccessful,
            clearErrors,
            resetAndClearErrors,
            defaults
        }) => (
        <>
        <input type="text" name="name" />

        {errors.name && <div>{errors.name}</div>}

        <button type="submit" disabled={processing}>
            {processing ? 'Creating...' : 'Create User'}
        </button>

        {wasSuccessful && <div>User created successfully!</div>}
        </>
    )}
    </Form>
)

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v4 rules ===

## Tailwind 4

- Always use Tailwind CSS v4 - do not use the deprecated utilities.
- `corePlugins` is not supported in Tailwind v4.
- In Tailwind v4, configuration is CSS-first using the `@theme` directive — no separate `tailwind.config.js` file is needed.
<code-snippet name="Extending Theme in CSS" lang="css">
@theme {
  --color-brand: oklch(0.72 0.11 178);
}
</code-snippet>

- In Tailwind v4, you import Tailwind using a regular CSS `@import` statement, not using the `@tailwind` directives used in v3:

<code-snippet name="Tailwind v4 Import Tailwind Diff" lang="diff">
   - @tailwind base;
   - @tailwind components;
   - @tailwind utilities;
   + @import "tailwindcss";
</code-snippet>


### Replaced Utilities
- Tailwind v4 removed deprecated utilities. Do not use the deprecated option - use the replacement.
- Opacity values are still numeric.

| Deprecated |	Replacement |
|------------+--------------|
| bg-opacity-* | bg-black/* |
| text-opacity-* | text-black/* |
| border-opacity-* | border-black/* |
| divide-opacity-* | divide-black/* |
| ring-opacity-* | ring-black/* |
| placeholder-opacity-* | placeholder-black/* |
| flex-shrink-* | shrink-* |
| flex-grow-* | grow-* |
| overflow-ellipsis | text-ellipsis |
| decoration-slice | box-decoration-slice |
| decoration-clone | box-decoration-clone |


=== laravel/fortify rules ===

## Laravel Fortify

Fortify is a headless authentication backend that provides authentication routes and controllers for Laravel applications.

**Before implementing any authentication features, use the `search-docs` tool to get the latest docs for that specific feature.**

### Configuration & Setup
- Check `config/fortify.php` to see what's enabled. Use `search-docs` for detailed information on specific features.
- Enable features by adding them to the `'features' => []` array: `Features::registration()`, `Features::resetPasswords()`, etc.
- To see the all Fortify registered routes, use the `list-routes` tool with the `only_vendor: true` and `action: "Fortify"` parameters.
- Fortify includes view routes by default (login, register). Set `'views' => false` in the configuration file to disable them if you're handling views yourself.

### Customization
- Views can be customized in `FortifyServiceProvider`'s `boot()` method using `Fortify::loginView()`, `Fortify::registerView()`, etc.
- Customize authentication logic with `Fortify::authenticateUsing()` for custom user retrieval / validation.
- Actions in `app/Actions/Fortify/` handle business logic (user creation, password reset, etc.). They're fully customizable, so you can modify them to change feature behavior.

## Available Features
- `Features::registration()` for user registration.
- `Features::emailVerification()` to verify new user emails.
- `Features::twoFactorAuthentication()` for 2FA with QR codes and recovery codes.
  - Add options: `['confirmPassword' => true, 'confirm' => true]` to require password confirmation and OTP confirmation before enabling 2FA.
- `Features::updateProfileInformation()` to let users update their profile.
- `Features::updatePasswords()` to let users change their passwords.
- `Features::resetPasswords()` for password reset via email.
</laravel-boost-guidelines>

---

## Cloud Engine — Domain Architecture (app/Core)

This project has a custom domain layer under `app/Core` following DDD principles. **Never put domain logic in controllers.**

### Execution Flows

```
Site management:
Controller → UseCase / EasyEngineEngine → RemoteCommandExecutor → SSHDriver → Remote VPS
                                     ↓
                              CommandNormalizer / CommandAuditLog

Provisioning:
ProvisioningController / server:recipe → RecipeRegistry → RecipeRunner → RemoteCommandExecutor → SSHDriver → Remote VPS
                                                      ↓
                                        ProvisioningRun / Recipe events / queued job
```

### Two Server Models (Critical Distinction)

There are **two separate Server classes** — always use the right one:

| Class | Location | Purpose |
|---|---|---|
| `App\Models\Server` | `app/Models/Server.php` | Eloquent ORM — persistence only |
| `App\Core\Servers\Models\Server` | `app/Core/Servers/Models/Server.php` | Domain entity — passed to `app/Core` services |

Convert between them with `$eloquentServer->toDomainModel()`. The domain model is a readonly value object. The Eloquent model stores SSH private keys encrypted via `encrypt()` in the `ssh_users` JSON column.

### SSH Users Structure

`ssh_users` is a JSON array column. Each entry:
```php
['username' => 'root', 'encrypted_private_key' => encrypt($rawKey)]
```
`ssh_execution_username` selects which user runs commands. Private keys are decrypted in-memory only inside `ServerConnectionService`.

### Adding a New Provisioning Engine

1. Create `app/Core/Engines/YourEngine/YourEngineEngine.php` implementing `EngineInterface`
2. Create `app/Core/Engines/YourEngine/YourEngineCommandBuilder.php` — use `escapeshellarg()` for all user input, never string concatenation
3. Register the engine in `app/Core/Engines/Registry/EngineRegistry.php` wiring (currently via `CoreServiceProvider`)
4. Register validator wiring in `EngineValidatorResolver` if the engine needs connection validation
5. Bind it in `CoreServiceProvider` if needed

### Shell Commands & Recipes Pattern

- Shell commands extend `App\Core\Commands\AbstractCommand` and implement `CommandInterface`
- Required methods: `id()`, `name()`, `description()`, `command()` (returns the shell string)
- Recipes live under `app/Core/Provisioning/Recipes/` and implement `ProvisioningRecipeInterface`
- Recipes are **declarative data holders** — they return ordered command objects and do not execute anything themselves
- Recipes are resolved via `RecipeRegistry` and executed via `RecipeRunner` (web requests dispatch `RunProvisioningRecipeJob`; CLI uses `server:recipe`)
- Provisioning recipes default to execution as `root`; specific recipes may opt into a selectable execution user via recipe metadata

### Custom Log Channel

SSH/engine activity logs to the `engines` channel. Use `Log::channel('engines')->info(...)` — not the default channel — for anything inside `app/Core/Drivers` or `app/Core/Engines`.

### PHP Conventions for app/Core

- All files must start with `declare(strict_types=1);`
- All classes are `final` unless designed for extension
- Use constructor property promotion
- PHPDoc `@return array{stdout: string, stderr: string, exitStatus: int}` shape types for engine results

---

## Git Commit Conventions

All commits in this repository must follow **[Conventional Commits](https://www.conventionalcommits.org/)**.

### Format

```
<type>(<scope>): <subject>

[optional body]

[optional footer(s)]
```

### Types

| Type | When to use |
|---|---|
| `feat` | New feature or capability |
| `fix` | Bug fix |
| `refactor` | Code change that is neither a fix nor a feature |
| `perf` | Performance improvement |
| `test` | Adding or fixing tests |
| `docs` | Documentation only |
| `style` | Formatting, whitespace (no logic change) |
| `chore` | Build process, dependencies, config |
| `ci` | CI/CD pipeline changes |
| `build` | Changes affecting the build system |

### Scope (optional, lowercase noun)

Use the area of the codebase being changed: `ssh`, `provisioning`, `engines`, `servers`, `recipes`, `security`, `ui`, `auth`, `api`, `migrations`, `tests`

### Subject Rules

- Imperative mood: `add`, `fix`, `remove` — not `added`, `fixes`, `removed`
- Max 72 characters
- No period at the end
- Lowercase after the colon

### Examples

```
feat(ssh): add multi-user SSH authentication support
fix(engines): handle missing exit status from phpseclib
refactor(provisioning): extract command building into EasyEngineCommandBuilder
feat(ui): add server connection status badge to index page
test(servers): add feature test for toDomainModel conversion
chore: update phpseclib to 3.1
feat(engines)!: rename runAction signature — BREAKING CHANGE
```

### Breaking Changes

Append `!` after the type/scope and add a `BREAKING CHANGE:` footer:

```
feat(engines)!: change EngineInterface runAction return shape

BREAKING CHANGE: runAction now returns an EngineResult value object instead of a plain array.
```
