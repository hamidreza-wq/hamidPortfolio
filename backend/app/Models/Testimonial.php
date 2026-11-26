<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    public const STATUSES = ['pending' => 'Waiting for review', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public const SOURCES = ['manual' => 'Added by me', 'website' => 'Website form', 'linkedin' => 'LinkedIn', 'email' => 'Email', 'github' => 'GitHub', 'other' => 'Other'];

    protected $fillable = [
        'name', 'role', 'company', 'quote', 'rating', 'source', 'source_url', 'email',
        'consent_at', 'status', 'is_featured', 'sort_order', 'given_on', 'ip_hash',
        'reviewed_by', 'reviewed_at', 'internal_note',
    ];

    protected $hidden = ['email', 'ip_hash', 'internal_note'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'consent_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'given_on' => 'date',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'approved')
            ->orderByDesc('is_featured')->orderBy('sort_order')->orderByDesc('given_on')->orderByDesc('id');
    }

    /** The shape the public section renderer already understands. */
    public function toSectionItem(): array
    {
        $byline = implode(', ', array_filter([$this->role, $this->company]));
        if ($this->rating) {
            $byline = trim($byline.' · '.str_repeat('★', $this->rating).str_repeat('☆', 5 - $this->rating));
        }

        return ['title' => $this->name, 'subtitle' => ltrim($byline, ' ·'), 'body' => '“'.$this->quote.'”'];
    }
}
