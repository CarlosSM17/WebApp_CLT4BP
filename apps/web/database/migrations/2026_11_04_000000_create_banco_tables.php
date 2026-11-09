<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bancos compartidos entre cursos (ADR 0009): temas (objetivos de aprendizaje) e ítems. El catálogo se carga de
 * resources/banco con `banco:cargar`; lo que un curso toma de aquí se copia, así que editarlo no afecta al banco.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_topics', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 64)->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->cascadeOnDelete(); // null: del catálogo
            $table->string('titulo', 160);
            $table->text('descripcion');                            // verbo observable + condición + criterio
            $table->string('tipo', 16)->default('habilidad');       // conocimiento | habilidad | actitud
            $table->jsonb('evaluacion');                            // métodos con que se comprueba
            $table->jsonb('prerrequisitos');                        // claves de otros temas
            $table->jsonb('lenguajes');                             // vacío: cualquiera
            $table->unsignedSmallInteger('orden')->default(1000);   // desempate de la ruta entre temas sin dependencia
            $table->unsignedSmallInteger('horas')->default(4);
            $table->text('complejidad')->nullable();                // qué hace a este tema más complejo que el anterior
            $table->timestamps();
        });

        Schema::create('bank_items', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 64)->unique();                 // estable entre cargas, p. ej. ciclos-c-03
            $table->foreignId('bank_topic_id')->constrained()->cascadeOnDelete();
            $table->string('tipo', 24);                             // los mismos de items.tipo
            $table->string('nivel', 16);                            // recall | comprension | practica
            $table->jsonb('lenguajes');                             // en qué lenguajes sirve; vacío: cualquiera
            $table->jsonb('enunciado');
            $table->jsonb('clave')->nullable();
            $table->jsonb('casos_prueba')->nullable();
            $table->text('solucion')->nullable();
            $table->jsonb('verificacion')->nullable();              // {programa, entrada, salida}: comprueba predicciones y Parsons
            $table->timestamps();
            $table->index(['bank_topic_id', 'tipo']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('bank_item_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->unique(['course_id', 'bank_item_id']);          // un ítem del banco entra una sola vez a cada curso
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'bank_item_id']);
            $table->dropConstrainedForeignId('bank_item_id');
        });
        Schema::dropIfExists('bank_items');
        Schema::dropIfExists('bank_topics');
    }
};
