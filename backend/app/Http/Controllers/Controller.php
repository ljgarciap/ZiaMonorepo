<?php

namespace App\Http\Controllers;

/**
 * @OA\Info(
 *     title="Zia Carbon Footprint API",
 *     version="1.0.0",
 *     description="API documentation for the Zia Carbon Footprint Dashboard and Backend logic.",
 *     @OA\Contact(
 *         email="support@zia.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="Dynamic Host"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
abstract class Controller
{
    //
}
