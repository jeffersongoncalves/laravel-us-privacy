<?php

namespace JeffersonGoncalves\UsPrivacy\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Fired by anonymizePersonalData() after saving. $columns lists the columns changed (Keep columns excluded).
 */
class PersonalDataAnonymized
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(public Model $model, public array $columns) {}
}
