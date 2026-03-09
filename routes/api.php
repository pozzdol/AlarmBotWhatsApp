<?php

use App\Http\Controllers\Api\WhatsappWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/webhook/whatsapp', [WhatsappWebhookController::class, 'handle']);
