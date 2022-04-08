<?php
namespace Levtechdev\SimPaas\Database\Redis;

use Illuminate\Redis\RedisManager;
use Levtechdev\SimPaas\Database\DbAdapterInterface;

class RedisAdapter extends RedisManager implements DbAdapterInterface
{

}