<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;
use Throwable;

#[Fillable([
    'name',
    'email',
    'phone',
    'password',
    'password_hint',
    'username',
    'balance_vnd',
    'frozen_vnd',
    'bank_name',
    'bank_account_number',
    'bank_account_name',
    'created_by',
    'status',
    'locked_at',
    'locked_by',
    'lock_reason',
    'last_login_at',
    'last_login_ip',
])]
#[Hidden(['password', 'password_hint', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Admin who created this user (null for self-registered / seeded accounts).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    /**
     * The actor that locked this user, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function locker(): BelongsTo
    {
        return $this->belongsTo(self::class, 'locked_by');
    }

    /**
     * Bets placed by the user across events / rounds.
     *
     * @return HasMany<EventBet, $this>
     */
    public function eventBets(): HasMany
    {
        return $this->hasMany(EventBet::class, 'user_id');
    }

    /**
     * Customers / users that this admin/staff has created.
     *
     * @return HasMany<User, $this>
     */
    public function managedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'created_by');
    }

    public function isLocked(): bool
    {
        return $this->status === UserStatus::Locked;
    }

    /**
     * Wallet balance the user can spend (bet, withdraw) — total balance minus admin-frozen amount.
     */
    public function availableVnd(): int
    {
        $total = (int) $this->balance_vnd;
        $frozen = (int) ($this->frozen_vnd ?? 0);

        return max(0, $total - $frozen);
    }

    /**
     * Store a new login password (hashed) and keep the encrypted hint in sync so admin/staff "xem mật khẩu" reflects self-service changes.
     */
    public function syncPasswordAndHintFromPlain(string $plain): void
    {
        $this->forceFill([
            'password' => $plain,
            'password_hint' => $plain,
        ])->save();
    }

    /**
     * Plain password kept encrypted at rest so admins/staff can reveal it.
     */
    protected function passwordHint(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (empty($value)) {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (Throwable) {
                    return null;
                }
            },
            set: function ($value) {
                if ($value === null || $value === '') {
                    return ['password_hint' => null];
                }

                return ['password_hint' => Crypt::encryptString((string) $value)];
            },
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'balance_vnd' => 'integer',
            'frozen_vnd' => 'integer',
            'status' => UserStatus::class,
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
