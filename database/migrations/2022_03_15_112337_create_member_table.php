<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('member', function (Blueprint $table) {
            $table->id();
            $table->string('s_n'); //small letters bet
            $table->string('Passport')->unique();;
            $table->string('firstName');
            $table->string('LastName');
            $table->string('OtherName');
            $table->string('Exam_number');
            $table->string('Email')->unique();
            $table->string('Grade');
            $table->string('License');
            $table->string('Phone')->unique();
            $table->string('Address');
            $table->string('Employer');
            $table->string('dob');
            $table->string('State');
            $table->string('Induction');
            $table->string('Submitted');
            $table->string('Accepted');
            $table->string('Chapter');
            $table->string('Mem_no')->unique();;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('member');
    }
};
