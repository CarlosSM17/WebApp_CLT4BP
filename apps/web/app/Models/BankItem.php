<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pregunta o problema del catálogo. Un curso lo usa a través de su copia en `items`. */
#[Fillable(['codigo', 'bank_topic_id', 'tipo', 'nivel', 'lenguajes', 'enunciado', 'clave', 'casos_prueba', 'solucion', 'verificacion'])]
#[Hidden(['verificacion'])]
class BankItem extends Model
{
    protected function casts(): array
    {
        return ['lenguajes' => 'array', 'enunciado' => 'array', 'clave' => 'array', 'casos_prueba' => 'array', 'verificacion' => 'array'];
    }

    public function tema(): BelongsTo
    {
        return $this->belongsTo(BankTopic::class, 'bank_topic_id');
    }

    /** Vacío: el ítem no depende del lenguaje (sin código). */
    public function scopeParaLenguaje(Builder $consulta, string $lenguaje): void
    {
        $consulta->where(fn ($q) => $q->whereJsonLength('lenguajes', 0)->orWhereJsonContains('lenguajes', $lenguaje));
    }

    public function sirveEn(string $lenguaje): bool
    {
        return $this->lenguajes === [] || in_array($lenguaje, $this->lenguajes, true);
    }
}
