<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransferItemCustomerItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_item_customer_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transfer_item_customer_id')->index();
            $table->unsignedInteger('item_id')->index();
            $table->string('item_name');
            $table->unsignedDecimal('quantity', 65, 30);
            $table->datetime('expiry_date')->nullable();
            $table->string('production_number')->nullable();
            $table->string('unit');
            $table->decimal('converter', 65, 30);
            $table->string('notes')->nullable();
            $table->unsignedDecimal('stock', 65, 30);
            $table->unsignedDecimal('balance', 65, 30);

            $table->foreign('transfer_item_customer_id')
                ->references('id')->on('transfer_item_customers')
                ->onUpdate('cascade')
                ->onDelete('cascade');

            $table->foreign('item_id')
                ->references('id')->on('items')
                ->onUpdate('restrict')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_item_customer_items');
    }
}
