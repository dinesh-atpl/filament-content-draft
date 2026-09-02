<?php

namespace Konectar\FilamentContentDraft\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDraft extends Model
{
    protected $fillable = ['user_id', 'key', 'payload'];

    public function getTable(): string
    {
        return (string) config('content-draft.table_name', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /**
     * Relationship to the configured user model.
     */
    public function user(): BelongsTo
    {
        $userModel = config('content-draft.user_model', User::class);

        return $this->belongsTo($userModel);
    }
}
