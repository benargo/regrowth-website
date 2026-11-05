<?php

namespace App\Actions\RecruitmentMacros;

use App\Models\RecruitmentMacro;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Creates a recruitment macro recorded against its creator, at the end of the macro order.
 */
class CreateRecruitmentMacro
{
    use AsAction;

    /**
     * @param  array{title: string, type: string, content: string}  $attributes
     */
    public function handle(User $creator, array $attributes): RecruitmentMacro
    {
        return RecruitmentMacro::create([
            ...$attributes,
            'created_by' => $creator->id,
        ]);
    }
}
