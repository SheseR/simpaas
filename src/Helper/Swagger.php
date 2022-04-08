<?php

namespace Levtechdev\Simpaas\Helper;

/**
 * @OA\Info(
 *   title="Service v1.0",
 *   version="1.0",
 *   x={
 *     "complex-type": {
 *       "supported":{
 *         {"version": "1.0", "level": "api"},
 *       }
 *     }
 *   }
 * ),
 * @OA\Server(
 *         description="Integration Management System microservice API",
 *         url=API_URL
 * ),
 * @OA\Schemes(format="http"),
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      in="header",
 *      name="Authorization",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 *      description="Auth header format for Swagger: {JWT token}",
 * )
 */
class Swagger extends Core
{
}
