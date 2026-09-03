# Filament Content Draft

[![Latest Version on Packagist](https://img.shields.io/packagist/v/konectar/filament-content-draft.svg?style=flat-square)](https://packagist.org/packages/konectar/filament-content-draft)
[![Filament 5.x](https://img.shields.io/badge/Filament-5.x-f59e0b.svg?style=flat-square)](https://filamentphp.com)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.2-777bb4.svg?style=flat-square)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

A robust, enterprise-grade auto-save and draft recovery plugin for **Filament 5.x**.

**Filament Content Draft** protects your users from lost work due to accidental tab closures, session timeouts, network blips, or sudden browser crashes. It silently auto-saves form input in the background and offers non-destructive recovery when returning to the form.

---

## 🌟 Key Features

- ⚡ **Zero-Friction Auto-Save**: Seamlessly polls and stores form changes in the background using Livewire's `wire:poll`.
- 🛡️ **Non-Destructive Recovery**: Displays a sleek inline notification banner when an unsaved draft is detected, giving the user full control to **Restore** or **Discard**.
- 📄 **Full Support for Standard & Modal Forms**:
  - `RecoversContentDraft`: For dedicated `CreateRecord`, `EditRecord`, and custom Filament form pages.
  - `RecoversModalContentDraft`: For inline modal actions (`CreateAction`, `EditAction`, and Table Actions).
- 🧹 **Automatic Cleanup**: Automatically deletes drafts when forms are successfully submitted, saved, or when the user discards them.
- 🔒 **Security-First (Sensitive Field Stripping)**: Automatically excludes passwords and sensitive inputs from drafts recursively.
- 🔑 **Smart ID & Database Agnostic**: Supports MySQL, PostgreSQL, SQLite, and MariaDB. Automatically detects and respects BigInt, UUID, or ULID primary keys on your User model.
- 🎨 **Flexible Indicator Styles**: Displays a real-time "Draft saved at HH:MM:SS" badge either inline beneath the form or as a floating badge in any corner of the viewport.
- 🔒 **Optional Form Locking**: Can optionally freeze/disable form inputs until the user resolves a pending draft restore.
- 🕒 **Automated Daily Pruning**: Includes an artisan command (`drafts:prune`) pre-scheduled to remove stale drafts older than *N* days.

---

## 📦 Installation

### 1. Require the Package

```bash
composer require konectar/filament-content-draft
```

### 2. Publish Configuration and Migrations

Publish the package configuration file and database migration:

```bash
php artisan vendor:publish --tag="content-draft-config"
php artisan vendor:publish --tag="content-draft-migrations"
```

### 3. Run Migrations

```bash
php artisan migrate
```

This creates the `content_drafts` table with unique constraints on `[user_id, key]`.

### 4. Register the Plugin in Your Panel Provider

Add `ContentDraftPlugin` to your Filament Panel provider (e.g. `app/Providers/Filament/AdminPanelProvider.php`):

```php
use Konectar\FilamentContentDraft\ContentDraftPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        // ... other panel configurations
        ->plugins([
            ContentDraftPlugin::make(),
        ]);
}
```

---

## 🚀 Quick Start & Usage

The package provides two traits depending on whether your form lives on a **dedicated page** or inside a **modal action**.

---

### Method A: Standard Resource Pages (Create & Edit)

Use the `RecoversContentDraft` trait in your dedicated `CreateRecord` or `EditRecord` page classes.

#### Create Record Page:
```php
namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\CreateRecord;
use Konectar\FilamentContentDraft\Concerns\RecoversContentDraft;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    use RecoversContentDraft;
}
```

#### Edit Record Page:
```php
namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use Filament\Resources\Pages\EditRecord;
use Konectar\FilamentContentDraft\Concerns\RecoversContentDraft;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    use RecoversContentDraft;
}
```

**That's it!**
- While editing, any changes will be automatically saved every 5 seconds (configurable).
- When a user leaves and returns, a banner prompt appears: **"Unsaved draft available"** with **Restore** and **Discard** options.
- Once the user clicks "Save" or "Create", the draft is automatically removed.

---

### Method B: Modal Actions (Table / Header / Page Actions)

If your resource or page manages records via modal dialogs (`CreateAction` or `EditAction` in tables or page headers), use the `RecoversModalContentDraft` trait on the parent Page or List class.

```php
namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Konectar\FilamentContentDraft\Concerns\RecoversModalContentDraft;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    use RecoversModalContentDraft;

    public function table(Table $table): Table
    {
        return $table
            ->headerActions([
                CreateAction::make()
                    ->form([/* ... fields ... */]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([/* ... fields ... */]),
            ]);
    }
}
```

The modal trait automatically:
- Injects the draft notification banner right above the modal form schema.
- Mounts the auto-save poller inside the modal action.
- Detects the current record ID when editing table rows.
- Wipes the draft once the modal action completes successfully.

---

## ⚙️ Advanced Customization

### 1. Custom Draft Keys

By default, draft keys are resolved automatically:
- **Create pages/modals**: `{resource-slug}-create` (e.g., `posts-create`)
- **Edit pages/modals**: `{resource-slug}-edit-{record_id}` (e.g., `posts-edit-42`)

You can override how keys are generated if you have tenant-scoped data, multiple forms, or custom routing:

#### In Standard Pages (`RecoversContentDraft`):
```php
protected function contentDraftKey(): string
{
    return 'tenant-' . auth()->user()->tenant_id . '-post-edit-' . $this->getRecord()->getKey();
}
```

#### In Modal Actions (`RecoversModalContentDraft`):
```php
protected function createDraftKey(): string
{
    return request('client_id', 1) . '_case_type-create';
}

protected function editDraftKey(int|string|null $recordId): string
{
    return request('client_id', 1) . '_case_type-edit-' . $recordId;
}
```

---

### 2. Excluding Sensitive Fields

By default, common password fields are ignored. You can specify additional fields that should never be saved to drafts (e.g., credit card details, API secrets, SSNs):

#### Globally via `config/content-draft.php`:
```php
'except_fields' => [
    'password',
    'password_confirmation',
    'credit_card_number',
    'cvv',
    'api_token',
],
```

#### Per-Page:
```php
// In standard pages:
public function contentDraftExcept(): array
{
    return ['temp_token', 'two_factor_code'];
}

// In modal pages:
public function modalContentDraftExcept(): array
{
    return ['secret_key'];
}
```

---

### 3. Lock Form While Restore Decision is Pending

To prevent users from modifying or dirtying the form before making a decision to **Restore** or **Discard** an existing draft, you can enable form locking:

#### Via Plugin Builder:
```php
ContentDraftPlugin::make()
    ->lockFormWhileDraftPending(true)
```

#### Or via `.env`:
```env
CONTENT_DRAFT_LOCK_FORM=true
```

When enabled, the entire form schema is automatically set to `disabled` until the user chooses either **Restore** or **Discard**.

---

### 4. Customizing the Save Indicator Position

You can place the "Draft saved at..." badge right beneath the form or as a floating badge in any corner of the screen:

#### Available Positions:
| Position Value | Description |
| :--- | :--- |
| `'under-form'` *(default)* | Clean inline badge rendered directly beneath the form inputs. |
| `'bottom-right'` | Floating pill at the bottom-right corner (auto-fades after 4s). |
| `'bottom-left'` | Floating pill at the bottom-left corner (auto-fades after 4s). |
| `'top-right'` | Floating pill at the top-right corner (auto-fades after 4s). |
| `'top-left'` | Floating pill at the top-left corner (auto-fades after 4s). |

#### Configuration:
```php
ContentDraftPlugin::make()
    ->position('bottom-right')
```
Or via `.env`:
```env
CONTENT_DRAFT_POSITION=bottom-right
```

---

### 5. Custom Poll Interval

Change how frequently drafts are saved (in seconds):

```php
ContentDraftPlugin::make()
    ->pollInterval(10) // Poll every 10 seconds
```
Or via `.env`:
```env
CONTENT_DRAFT_POLL_INTERVAL=10
```


---

## 🧹 Draft Pruning & Maintenance

Content drafts that are abandoned by users can accumulate over time. The plugin provides a built-in prune command:

```bash
# Prune drafts older than default (7 days)
php artisan drafts:prune

# Prune drafts older than 3 days
php artisan drafts:prune --days=3
```

### Automated Daily Pruning
The package's `ContentDraftServiceProvider` automatically registers the command in your Laravel scheduler to run **daily**:

```php
$schedule->command('drafts:prune')->daily();
```

Make sure your server has the Laravel scheduler running in cron (`* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1`).

---

## 📋 Configuration Reference

Here is the complete reference for `config/content-draft.php`:

```php
return [
    /*
    | Database table name used to store drafts.
    | Default: 'content_drafts'
    */
    'table_name' => env('CONTENT_DRAFT_TABLE_NAME', 'content_drafts'),

    /*
    | How often (in seconds) the form auto-saves a draft via wire:poll.
    | Default: 5
    */
    'poll_interval' => env('CONTENT_DRAFT_POLL_INTERVAL', 5),

    /*
    | Drafts older than this many days are deleted by `drafts:prune`.
    | Default: 7
    */
    'prune_after_days' => env('CONTENT_DRAFT_PRUNE_DAYS', 7),

    /*
    | Fully-qualified class name of your User model.
    | Used for foreign keys and relationships.
    */
    'user_model' => App\Models\User::class,

    /*
    | Position of the draft indicator badge:
    | 'under-form', 'bottom-right', 'bottom-left', 'top-right', 'top-left'
    */
    'position' => env('CONTENT_DRAFT_POSITION', 'under-form'),

    /*
    | Whether the form should be disabled/locked until the user clicks
    | "Restore" or "Discard" from the unsaved draft banner.
    */
    'lock_form_while_draft_pending' => env('CONTENT_DRAFT_LOCK_FORM', false),

    /*
    | Form fields that should never be stored in content drafts.
    */
    'except_fields' => [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
    ],
];
```

---

## 🧠 How It Works (Under the Hood)

1. **Initial Mount**:
   - When a page or modal loads, the trait takes a snapshot of the initial state (`$contentDraftReferenceState`). On edit pages, this is the clean record loaded from the database; on create pages, it's the defaults.
   - It queries the `content_drafts` table for `[user_id, key]`. If a draft exists, `$contentDraftRestorePending` is set to `true`, and the restore banner is rendered.
2. **Race-Condition Protection**:
   - While a draft restore prompt is pending, the auto-saver is **strictly paused**. This guarantees that the fresh initial/empty form will never overwrite an existing draft before the user decides.
3. **Dirty-State Detection**:
   - Every *N* seconds, `wire:poll` triggers `saveDraft()`.
   - If the form is empty, saving is skipped.
   - If the form content equals the reference state (e.g., user erased their changes or pressed undo), the existing draft is automatically cleaned up.
   - Otherwise, the serialized payload is saved to the database via an idempotent `updateOrCreate()`.
4. **Restoration & Continuation**:
   - Clicking **Restore** fills the form with the saved draft payload. The draft row stays safely in the database until the actual record is saved, so closing the tab immediately after restoring will not lose the restored data.
   - Clicking **Discard** deletes the draft and unfreezes the form.
5. **Form Submission**:
   - When the user triggers any submit action (`save`, `create`, `createAnother`) or when the `RecordSaved` / `ActionCalled` event fires, the draft row is automatically deleted.

---

## 🛠️ Testing & Verification

To verify that the module works smoothly in your project:
1. Navigate to any resource create or edit page with `RecoversContentDraft`.
2. Type in some text and wait for the **"Draft saved at HH:MM:SS"** indicator.
3. Refresh the page or close the tab and reopen it.
4. Verify the **"Unsaved draft available"** banner appears with **Restore** and **Discard** buttons.
5. Click **Restore** and confirm all your fields are re-populated.
6. Click **Save** and verify that the draft record is cleared from the `content_drafts` table.

---

## 🤝 Contributing & Support

- If you encounter issues or have feature requests, please submit an issue or pull request to the repository.
- Built and maintained with ❤️ by the Konectar Team.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
