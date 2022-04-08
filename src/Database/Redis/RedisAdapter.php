<?php
namespace Levtechdev\Simpaas\Database\Redis;

use Illuminate\Redis\RedisManager;
use Levtechdev\Simpaas\Database\DbAdapterInterface;

class RedisAdapter extends RedisManager implements DbAdapterInterface
{

}