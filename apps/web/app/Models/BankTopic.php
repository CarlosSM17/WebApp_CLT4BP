<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Tema del banco de objetivos: del catálogo (sin dueño) o propio de un instructor. */
#[Fillable(['clave', 'owner_id', 'titulo', 'descripcion', 'tipo', 'evaluacion', 'prerrequisitos', 'lenguajes', 'orden', 'horas', 'complejidad'])]
class BankTopic extends Model
{
    protected function casts(): array
    {
        return ['evaluacion' => 'array', 'prerrequisitos' => 'array', 'lenguajes' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BankItem::class);
    }

    /** Lo que ve un instructor: el catálogo y sus propios temas. */
    public function scopeVisiblesPara(Builder $consulta, User $user): void
    {
        $consulta->where(fn ($q) => $q->whereNull('owner_id')->orWhere('owner_id', $user->id));
    }

    /** El mismo tema sirve para el curso si no restringe lenguajes o incluye el del curso. */
    public function sirveEn(string $lenguaje): bool
    {
        return $this->lenguajes === [] || in_array($lenguaje, $this->lenguajes, true);
    }
}
