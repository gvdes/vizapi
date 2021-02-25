<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use App\Mail\TestMail;

class MailController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function welcome(){
        $data = [];
        /* Mail::send(null,$data, function($message){
            $message->to('josecarlos19979@hotmail.com', 'Carlos Hdz')->subject('Welcome!');
        }); */
        Mail::to('josecarlos19979@hotmail.com')->send(new TestMail);
    }
}
