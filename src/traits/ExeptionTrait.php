<?php

namespace App\traits;

trait ExeptionTrait
{
    public const EMPTY_DATA = "The received data is empty";
    public const EMPTY_UUID = "The identifier is empty";
    public const ERROR_FILEDS_DATA = "The filed data is invalid: %s";
    public const ERROR_FILED_REQUIRED = "The filed is required: %s";
    public const USER_NOT_FOUND = "User not found";
    public const USER_ALREADY_EXIST = "User already exist";
    public const WEBSITE_NOT_FOUND = "Website not found";
    public const WEBSITE_CONTRACT_ALREADY_EXIST = "Website contract already exist";
    public const WEBSITE_CONTRACT_NOT_FOUND = "Website contract not found";
    public const MAINTENANCE_CONTRACT_NOT_FOUND = "Website maintenance Contract not found";
    public const MAINTENANCE_CONTRACT_ALREADY_EXISTS = "Website maintenance already exists";
    public const PARAMETERS_NOT_FOUND = "Parameters not found";
    public const SUCCESS_RESPONSE = ['success' => true];
    public const ERROR_RESPONSE = ['success' => true];
}