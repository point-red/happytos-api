<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferItemCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_item_customers', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('warehouse_id')->index();
            $table->unsignedInteger('customer_id')->index();
            $table->unsignedInteger('expedition_id')->index();
            $table->string('plat');
            $table->string('stnk');
            $table->string('phone');

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onUpdate('restrict')->onDelete('restrict');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
            $table->foreign('expedition_id')->references('id')->on('expeditions')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_item_customers');
    }
}
