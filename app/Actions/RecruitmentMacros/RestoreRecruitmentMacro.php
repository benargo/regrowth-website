<?php

namespace App\Actions\RecruitmentMacros;

use App\Models\RecruitmentMacro;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Restores a trashed recruitment macro and moves it to the end of the macro order.
 */
class RestoreRecruitmentMacro
{
    use AsAction;

    public function handle(RecruitmentMacro $macro): RecruitmentMacro
    {
        $macro->setHighestOrderNumber();
        $macro->restore();

        return $macro;
    }
}
