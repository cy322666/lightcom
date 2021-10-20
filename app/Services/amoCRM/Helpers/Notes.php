<?php


namespace App\Services\amoCRM\Helpers;

use App\Services\amoCRM\Client;

abstract class Notes
{
    public static function add($model, array $values)
    {
        foreach ($values as $key => $value) {

            $array[] = ' - '.$key.' : '.$value;
        }

        $note = $model->createNote($type = 4);
        $note->text = implode("\n", $array);
        $note->save();

        return $note;
    }
}
