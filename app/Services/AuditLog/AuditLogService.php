<?php

namespace App\Services\AuditLog;

use App\Models\User;
use Aws\Credentials\Credentials;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\Marshaler;
use Aws\Sdk;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class AuditLogService implements AuditLogServiceInterface
{
    public function insertLog($model, $action, $attr = [])
    {
        try {
            if (!config('dynamodb.enabled')) {
                throw new Exception("DynamoDB is disabled");
            }

            if (Auth::guest()) {
                throw new Exception("Invalid Audit Log, Not authenticated.");
            }

            $client = $this->credentials();

            $dynamodb = $client->createDynamoDb();
            $tableName = config('dynamodb.table_name');
            $data = json_encode($attr);
            $user = Auth::user();

            $dynamodb->putItem([
                'TableName' => $tableName,
                'Item'      => [
                    config('dynamodb.sort_key')      => ['S' => config('dynamodb.sort_key') . time()],
                    config('dynamodb.partition_key') => ['S' => $user->id . '-' . time()],
                    'module_name'                    => ['S' => get_class($model)],
                    'user_id'                        => ['S' => $user->id],
                    'user_full_name'                 => ['S' => $user->firstname . ' ' . $user->lastname],
                    'payload'                        => ['S' => $data],
                    'created_date'                   => ['S' => Carbon::now()->format('Y-m-d')],
                    'created_time'                   => ['S' => Carbon::now()->format('H:i')],
                    'sort_key'                       => ['S' => config('dynamodb.environment')],
                    'action'                         => ['S' => $action],
                    'result'                         => ['S' => "SUCCESS"],
                ],
            ]);
        } catch (Exception $e) {
            logger()->critical('AuditLogService ' . $e->getMessage());
        }
    }

    public function loginLog($action, $attr)
    {
        try {
            if (!config('dynamodb.enabled')) {
                throw new Exception("DynamoDB is disabled");
            }

            $client = $this->credentials();

            $dynamodb = $client->createDynamoDb();
            $tableName = config('dynamodb.table_name');
            $data = json_encode($attr);
            $user = User::where('email', $attr['email'])->first();

            $dynamodb->putItem([
                'TableName' => $tableName,
                'Item'      => [
                    config('dynamodb.sort_key')      => ['S' => config('dynamodb.sort_key') . time()],
                    config('dynamodb.partition_key') => ['S' => $user->id . '-' . time()],
                    'user_id'                        => ['S' => $user->id],
                    'user_full_name'                 => ['S' => $user->firstname . ' ' . $user->lastname],
                    'payload'                        => ['S' => $data],
                    'created_date'                   => ['S' => Carbon::now()->format('Y-m-d')],
                    'created_time'                   => ['S' => Carbon::now()->format('H:i')],
                    'sort_key'                       => ['S' => config('dynamodb.environment')],
                    'action'                         => ['S' => $action],
                    'result'                         => ['S' => "SUCCESS"],
                ],
            ]);
        } catch (Exception $e) {
            logger()->critical('AuditLogService ' . $e->getMessage());
        }
    }

    public function credentials(): Sdk
    {
        if (
            is_null(config('dynamodb.access_key_id')) &&
            is_null(config('dynamodb.secret_access_key'))
        ) {
            throw new Exception("Dynamo DB Config Missing!");
        }

        $credentials = new Credentials(config('dynamodb.access_key_id'), config('dynamodb.secret_access_key'));

        return new Sdk([
            'version'     => 'latest',
            'region'      => config('dynamodb.default_region'),
            'credentials' => $credentials,
        ]);
    }

    public function getLogsByDate(string $from, string $to)
    {
        try {
            $client = $this->credentials();

            $dynamodb = $client->createDynamoDb();
            $marshaler = new Marshaler();
            $tableName = config('dynamodb.table_name');
            $search = request()->input('search');

            $eav = $marshaler->marshalJson('{
                ":sort_key": "' . config('dynamodb.environment') . '",
                ":from": "' . $from . '",
                ":to": "' . $to . '"
            }');

            $params = [
                'TableName'                 => $tableName,
                'ProjectionExpression'      => 'audit_logs_id, sort_key, module_name, user_id, payload, created_date, ' .
                    'user_full_name, created_time, #act',
                'FilterExpression'          => 'sort_key = :sort_key AND created_date between :from and :to',
                'ExpressionAttributeNames'  => ['#act' => 'action'],
                'ExpressionAttributeValues' => $eav,
            ];

            $logs = [];

            while (true) {
                $result = $dynamodb->scan($params);

                foreach ($result['Items'] as $i) {
                    $auditLogs = $marshaler->unmarshalItem($i);

                    $logs[] = [
                        'module_name'    => Arr::get($auditLogs, 'module_name'),
                        'user_id'        => Arr::get($auditLogs, 'user_id'),
                        'user_full_name' => Arr::get($auditLogs, 'user_full_name'),
                        'payload'        => [json_decode(Arr::get($auditLogs, 'payload'))],
                        'date'           => Arr::get($auditLogs, 'created_date') . ' ' . Arr::get($auditLogs, 'created_time'),
                        'action'         => Arr::get($auditLogs, 'action'),
                        'id'             => Arr::get($auditLogs, 'audit_logs_id'),
                    ];
                }

                if (isset($result['LastEvaluatedKey'])) {
                    $params['ExclusiveStartKey'] = $result['LastEvaluatedKey'];
                } else {
                    break;
                }
            }

            $collection = collect($logs);

            if ($search !== null) {
                $filtered = [];

                $filtered[] = $collection->filter(function ($item) use ($search) {
                    $payload = collect($item['payload']);

                    return
                        false !== stripos($item['module_name'], $search) ||
                        false !== stripos($item['user_id'], $search) ||
                        false !== stripos($item['user_full_name'], $search) ||
                        // false !== stripos($item['date'], $search) ||
                        false !== stripos($item['action'], $search) ||
                        false !== stripos($item['id'], $search) ||
                        false !== stripos($payload, $search);
                });

                return collect($filtered)->sortByDesc('date')->flatten(1)->values()->paginate(request('itemsPerPage') ?? 10);
            };

            return collect($collection)->sortByDesc('date')
                //->sortByDesc('time')
                ->values()->paginate(request('itemsPerPage') ?? 10);
        } catch (DynamoDbException $e) {
            logger()->info("Unable to scan:\n");
            logger()->info($e->getMessage() . "\n");
        } catch (Exception $e) {
            logger()->critical('AuditLogService ' . $e->getMessage());
        }
    }
}
