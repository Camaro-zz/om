<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOmGoodsSupplierTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('om_goods_supplier', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uid')->comment('添加人id')->default(0);
            $table->integer('goods_id')->comment('产品id')->default(0);
            $table->integer('supplier_id')->comment('供应商id')->default(0);
            $table->decimal('price',10,2)->comment('采购价')->default(0);
            $table->decimal('tax_price',10,2)->comment('含税采购价')->default(0);
            $table->integer('moq')->comment('起订量')->default(0);
            $table->string('mfrs_name',50)->comment('生产商名称')->default('');
            $table->tinyInteger('sort')->comment('排序，数值越大越靠前')->default(0);
            $table->tinyInteger('is_deleted')->comment('是否删除,1是删除')->default(0);
            $table->text('mark')->comment('供应商对产品的备注');
            $table->timestamps();
            $table->index('goods_id');
            $table->index('supplier_id');
            $table->comment = '产品和供应商关联表';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('om_goods_supplier');
    }
}
