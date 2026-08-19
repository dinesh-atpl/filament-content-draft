<?php

namespace Konectar\FilamentContentDraft\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDraft extends Model
{
    protected $fillable = ['user_id', 'key', 'payload'];

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
        $userModel = config('content-draft.user_model', \App\Models\User::class);

        return $this->belongsTo($userModel);
    }
}
