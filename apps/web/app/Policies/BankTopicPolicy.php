<?php

namespace App\Policies;

use App\Models\BankTopic;
use App\Models\User;

class BankTopicPolicy
{
    /** El catálogo no se edita desde la consola (es un archivo del repositorio) y un tema propio solo lo toca su dueño. */
    public function update(User $user, BankTopic $tema): bool
    {
        return $tema->owner_id !== null && $tema->owner_id === $user->id;
    }

    public function delete(User $user, BankTopic $tema): bool
    {
        return $this->update($user, $tema);
    }
}
