<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function send()
    {
        //выполняем работу с амо
        $transactions = Transaction::all();

        $profiles = Profile::all();
    }
}
