<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAuthorPreference extends Model
{
    use HasFactory;

    /**
     * The table model represents
     */
    protected $table = 'user_author_preferences';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'author_id'
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get author associated with the user preference
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(NewsAuthor::class, 'author_id', 'id');
    }
}
