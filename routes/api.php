<?php

use App\Features\InventoryEventProcessing\Controller as InventoryEventController;
use Illuminate\Support\Facades\Route;

Route::post('/_dev/messages', InventoryEventController::class);
