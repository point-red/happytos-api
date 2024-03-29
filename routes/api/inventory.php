<?php

Route::prefix('inventory')->namespace('Inventory')->group(function () {
    Route::get('inventory-recapitulations', 'InventoryRecapitulationController@index');
    Route::get('inventory-warehouse-recapitulations/{itemId}', 'InventoryWarehouseRecapitulationController@index');
    Route::get('inventory-details/{itemId}', 'InventoryDetailController@index');
    Route::get('inventory-dna/{itemId}', 'InventoryDnaController@index');
    Route::get('inventory-dna/{itemId}/all', 'InventoryDnaController@allDna');
    Route::get('inventory-dna/{itemId}/edit', 'InventoryDnaController@editDna');
    Route::get('inventory-warehouse-currentstock', 'InventoryWarehouseCurrentStockController@index');
    Route::apiResource('audits', 'InventoryAudit\\InventoryAuditController');
    Route::apiResource('usages', 'InventoryUsage\\InventoryUsageController');
    // Route::apiResource('inventory-corrections', 'InventoryCorrectionController');
    
    Route::post('transfer-items/export', 'TransferItem\\TransferItemController@export');
    Route::get('transfer-items/{id}/histories', 'TransferItem\\TransferItemHistoryController@index');
    Route::post('transfer-items/histories', 'TransferItem\\TransferItemHistoryController@store');
    Route::get('approval/transfer-items', 'TransferItem\\TransferItemApprovalController@index');
    Route::post('approval/transfer-items/send', 'TransferItem\\TransferItemApprovalController@sendApproval');

    Route::group(['middleware' => ['tenant.module-access:transfer item']], function () {
        Route::apiResource('transfer-items', 'TransferItem\\TransferItemController');
        Route::apiResource('transfer-item-customers', 'TransferItem\\TransferItemCustomerController');

        Route::post('transfer-items/{id}/approve', 'TransferItem\\TransferItemApprovalController@approve');
        Route::post('transfer-items/{id}/reject', 'TransferItem\\TransferItemApprovalController@reject');
        Route::post('transfer-items/{id}/close', 'TransferItem\\TransferItemController@close');
        Route::post('transfer-items/{id}/close-approve', 'TransferItem\\TransferItemCloseApprovalController@approve');
        Route::post('transfer-items/{id}/cancellation-approve', 'TransferItem\\TransferItemCancellationApprovalController@approve');
        Route::post('transfer-items/{id}/cancellation-reject', 'TransferItem\\TransferItemCancellationApprovalController@reject');

        Route::apiResource('receive-items', 'TransferItem\\ReceiveItemController');
        
        Route::post('receive-items/{id}/approve', 'TransferItem\\ReceiveItemApprovalController@approve');
        Route::post('receive-items/{id}/reject', 'TransferItem\\ReceiveItemApprovalController@reject');
        Route::post('receive-items/{id}/cancellation-approve', 'TransferItem\\ReceiveItemCancellationApprovalController@approve');
        Route::post('receive-items/{id}/cancellation-reject', 'TransferItem\\ReceiveItemCancellationApprovalController@reject');
        Route::get('receive-items/{id}/histories', 'TransferItem\\ReceiveItemHistoryController@index');
    });

    Route::post('receive-items/{id}/send', 'TransferItem\\ReceiveItemController@sendApproval');
    Route::post('receive-items/export', 'TransferItem\\ReceiveItemController@export');
    Route::post('receive-items/histories', 'TransferItem\\ReceiveItemHistoryController@store');
    Route::post('transfer-item-customers/{id}/approve', 'TransferItem\\TransferItemCustomerController@approve');
    Route::get('transfer-item-customers/{id}/histories', 'TransferItem\\TransferItemCustomerHistoryController@index');
    Route::post('transfer-item-customers/histories', 'TransferItem\\TransferItemCustomerHistoryController@store');
    Route::post('transfer-item-customers/{id}/cancellation-approve', 'TransferItem\\TransferItemCustomerController@cancellationApprove');
    Route::post('transfer-item-customers/export', 'TransferItem\\TransferItemCustomerController@export');
});
