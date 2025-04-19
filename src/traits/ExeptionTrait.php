<?php

namespace App\traits;

trait ExeptionTrait
{
    public const EMPTY_DATA = "The received data is empty";
    public const EMPTY_UUID = "The identifier is empty";
    public const ERROR_FILEDS_DATA = "The filed data is invalid: %s";
    public const ERROR_FILED_REQUIRED = "The filed is required: %s";
    public const USER_NOT_FOUND = "User not found";
    public const SUCCESS_RESPONSE = ['success' => true];
    public const ERROR_RESPONSE = ['success' => true];
}