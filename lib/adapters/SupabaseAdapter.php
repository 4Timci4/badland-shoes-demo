<?php
/**
 * Supabase Adapter
 * 
 * Mevcut SupabaseClient'ı DatabaseInterface'e uyumlu hale getirir
 */

require_once __DIR__ . '/../DatabaseInterface.php';

class SupabaseAdapter implements DatabaseInterface {
    private $client;
    private $lastError = null;
    
    public function __construct(SupabaseClient $client) {
        $this->client = $client;
    }
    
    /**
     * Veri seçme işlemi
     */
    public function select($table, $conditions = [], $columns = '*', $options = []) {
        try {
            $query = $this->buildSelectQuery($table, $conditions, $columns, $options);
            $response = $this->client->request($query);
            
            // Response formatını kontrol et
            if (isset($response['body'])) {
                return $response['body'];
            }
            
            // Eski format için geriye uyumluluk
            return is_array($response) ? $response : [];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::select - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Veri ekleme işlemi
     */
    public function insert($table, $data, $options = []) {
        try {
            $headers = [];
            $prefer = [];
            $query = $table;

            if (isset($options['returning'])) {
                $prefer[] = 'return=' . $options['returning'];
            }
            if (isset($options['on_conflict'])) {
                $query .= '?on_conflict=' . $options['on_conflict'];
                $prefer[] = 'resolution=merge-duplicates';
            }

            if (!empty($prefer)) {
                $headers['Prefer'] = implode(',', $prefer);
            }

            $response = $this->client->request($query, 'POST', $data, $headers);

            if (isset($response['body'])) {
                return $response['body'];
            }
            
            return ['affected_rows' => 1];

        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::insert - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Veri güncelleme işlemi
     */
    public function update($table, $data, $conditions, $options = []) {
        try {
            $query = $table . '?' . $this->buildConditions($conditions);
            
            $headers = [];
            if (isset($options['returning'])) {
                $headers['Prefer'] = 'return=representation';
                $response = $this->client->request($query, 'PATCH', $data, $headers);
                
                if (isset($response['body'])) {
                    return $response['body'];
                }
                return is_array($response) ? $response : [];
            }
            
            $response = $this->client->request($query, 'PATCH', $data, $headers);
            // Başarılı güncelleme işlemi
            return ['affected_rows' => 1];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::update - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Veri silme işlemi
     */
    public function delete($table, $conditions, $options = []) {
        try {
            $query = $table . '?' . $this->buildConditions($conditions);
            
            $headers = [];
            if (isset($options['returning'])) {
                $headers['Prefer'] = 'return=representation';
                $response = $this->client->request($query, 'DELETE', null, $headers);
                
                if (isset($response['body'])) {
                    return $response['body'];
                }
                return is_array($response) ? $response : [];
            }
            
            $response = $this->client->request($query, 'DELETE', null, $headers);
            // Başarılı silme işlemi
            return ['affected_rows' => 1];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::delete - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Ham SQL sorgusu çalıştırma
     */
    public function executeRawSql($sql, $params = []) {
        try {
            $response = $this->client->executeRawSql($sql, $params);
            return $response['body'] ?? [];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::executeRawSql - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * İlişkili veri çekme (JOIN)
     */
    public function selectWithJoins($table, $joins = [], $conditions = [], $columns = '*', $options = []) {
        try {
            // Supabase'de JOIN yerine nested select kullanıyoruz
            $selectParts = [];
            
            if (is_array($columns)) {
                $selectParts = $columns;
            } else {
                $selectParts[] = $columns;
            }
            
            // Join'ları Supabase syntax'ına çevir
            foreach ($joins as $join) {
                if (isset($join['table']) && isset($join['select'])) {
                    $selectParts[] = $join['table'] . '(' . $join['select'] . ')';
                }
            }
            
            $options['select'] = implode(',', $selectParts);
            
            return $this->select($table, $conditions, '*', $options);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::selectWithJoins - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Sayfa bazlı veri çekme
     */
    public function paginate($table, $conditions = [], $page = 1, $limit = 10, $options = []) {
        try {
            $offset = ($page - 1) * $limit;
            
            // Toplam sayıyı al
            $countOptions = array_merge($options, ['count_only' => true]);
            $total = $this->count($table, $conditions);
            
            // Verileri al
            $dataOptions = array_merge($options, [
                'limit' => $limit,
                'offset' => $offset
            ]);
            
            $data = $this->select($table, $conditions, $options['columns'] ?? '*', $dataOptions);
            
            $totalPages = $limit > 0 ? ceil($total / $limit) : 0;
            
            return [
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => $totalPages
            ];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::paginate - " . $e->getMessage());
            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit,
                'pages' => 0
            ];
        }
    }
    
    /**
     * Kayıt sayısı alma
     */
    public function count($table, $conditions = []) {
        try {
            $query = $table;
            if (!empty($conditions)) {
                $query .= '?' . $this->buildConditions($conditions);
            }
            
            $response = $this->client->request($query, 'GET', null, ['Prefer' => 'count=exact']);
            
            // Content-Range header'ından sayıyı al
            if (isset($response['headers']['content-range'])) {
                $range = explode('/', $response['headers']['content-range']);
                return isset($range[1]) ? intval($range[1]) : 0;
            }
            
            // Fallback olarak body'deki kayıt sayısını döndür
            return count($response['body'] ?? []);
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::count - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Önbellek temizleme
     */
    public function clearCache($key = null) {
        $this->client->clearCache($key);
    }
    
    /**
     * Transaction başlatma (Supabase'de HTTP düzeyinde desteklenmiyor)
     */
    public function beginTransaction() {
        // Supabase REST API'de transaction başlatma yoktur
        // Bu method sadece interface uyumluluğu için
    }
    
    /**
     * Transaction commit
     */
    public function commit() {
        // Supabase REST API'de transaction commit yoktur
    }
    
    /**
     * Transaction rollback
     */
    public function rollback() {
        // Supabase REST API'de transaction rollback yoktur
    }
    
    /**
     * Bağlantı durumu kontrolü
     */
    public function isConnected() {
        try {
            // Basit bir health check sorgusu
            $this->client->request('');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Son hata mesajını alma
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Mevcut SupabaseClient'a erişim (geriye uyumluluk için)
     */
    public function getClient() {
        return $this->client;
    }

    /**
     * Veritabanı fonksiyonlarını (RPC) çağırır
     */
    public function rpc($functionName, $params = []) {
        try {
            $endpoint = 'rpc/' . $functionName;
            $response = $this->client->request($endpoint, 'POST', $params);

            if (isset($response['body'])) {
                return $response['body'];
            }
            return is_array($response) ? $response : [];
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("SupabaseAdapter::rpc - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mevcut request metoduna proxy (geriye uyumluluk için)
     */
    public function request($endpoint, $method = 'GET', $data = null, $headers = [], $useCache = true) {
        return $this->client->request($endpoint, $method, $data, $headers, $useCache);
    }
    
    /**
     * SELECT sorgusu oluşturur
     */
    private function buildSelectQuery($table, $conditions, $columns, $options) {
        $queryParts = [];
        
        // Sütunları ayarla
        $selectString = '';
        if ($columns !== '*' && !empty($columns)) {
            if (is_array($columns)) {
                $selectString = 'select=' . urlencode(implode(',', $columns));
            } else {
                $selectString = 'select=' . urlencode($columns);
            }
        }
        if (isset($options['select'])) {
            $selectString = 'select=' . urlencode($options['select']);
        }
        if ($selectString) {
            $queryParts[] = $selectString;
        }

        // Koşulları ekle
        if (!empty($conditions)) {
            $conditionString = $this->buildConditions($conditions);
            if ($conditionString) {
                $queryParts[] = $conditionString;
            }
        }
        
        // Limit ve offset
        if (isset($options['limit'])) {
            $queryParts[] = 'limit=' . $options['limit'];
        }
        if (isset($options['offset'])) {
            $queryParts[] = 'offset=' . $options['offset'];
        }
        
        // Sıralama
        if (isset($options['order'])) {
            $queryParts[] = 'order=' . $this->convertOrderBy($options['order']);
        }
        
        $query = $table;
        if (!empty($queryParts)) {
            $query .= '?' . implode('&', $queryParts);
        }
        
        error_log("SupabaseAdapter built query: " . $query);
        return $query;
    }
    
    /**
     * Koşulları Supabase formatına çevirir
     */
    private function buildConditions($conditions) {
        $parts = [];
        
        foreach ($conditions as $key => $value) {
            // "or" anahtarı için özel işlem
            if (strtolower($key) === 'or' && is_array($value)) {
                $or_parts = [];
                foreach ($value as $or_condition) {
                     // Her bir or koşulunu 'field=operator.value' formatına çevir
                    $or_key = key($or_condition);
                    $or_val_array = $or_condition[$or_key];
                    $or_operator = $this->convertOperator($or_val_array[0]);
                    $or_value = $or_val_array[1];
                    $or_parts[] = "{$or_key}.{$or_operator}.{$or_value}";
                }
                if (!empty($or_parts)) {
                    $parts[] = 'or=(' . implode(',', $or_parts) . ')';
                }
                continue;
            }

            if (is_array($value)) {
                $operator = strtoupper($value[0]);
                $val = $value[1];
                
                if ($operator === 'IN' || $operator === 'NOT IN') {
                    if (is_array($val)) {
                        // Quote string values for the IN clause
                        $quoted_vals = array_map(function($item) {
                            if (is_string($item)) {
                                return '"' . trim($item, '"') . '"';
                            }
                            return $item;
                        }, $val);
                        $parts[] = $key . '=' . $this->convertOperator($operator) . '.(' . implode(',', $quoted_vals) . ')';
                    }
                } elseif ($this->convertOperator($operator) === 'ov') {
                    if (is_array($val)) {
                        $formatted_val = $this->formatArrayForPostgreSQL($val);
                        $parts[] = $key . '=' . $this->convertOperator($operator) . '.' . $formatted_val;
                    }
                }
                else {
                    if ($val === null) {
                        $parts[] = $key . '=is.null';
                    } else {
                        $parts[] = $key . '=' . $this->convertOperator($operator) . '.' . urlencode($val);
                    }
                }
            } else {
                if ($value === null) {
                    $parts[] = $key . '=is.null';
                } elseif ($value === '') {
                    $parts[] = $key . '=is.null';
                } elseif (is_bool($value)) {
                    $parts[] = $key . '=eq.' . ($value ? 'true' : 'false');
                } else {
                    $parts[] = $key . '=eq.' . urlencode($value);
                }
            }
        }
        
        return implode('&', $parts);
    }
    
    /**
     * Koşul string'ini parse eder
     */
    private function parseConditions($conditionString) {
        $parts = [];
        $conditions = explode('&', $conditionString);
        
        foreach ($conditions as $condition) {
            if (strpos($condition, '=') !== false) {
                list($key, $value) = explode('=', $condition, 2);
                $parts[$key] = $value;
            }
        }
        
        return $parts;
    }
    
    /**
     * Operatörleri Supabase formatına çevirir
     */
    private function convertOperator($operator) {
        $operatorMap = [
            '=' => 'eq',
            '!=' => 'neq',
            '<>' => 'neq',
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            'LIKE' => 'like',
            'ILIKE' => 'ilike',
            'IN' => 'in',
            'NOT IN' => 'not.in',
            '&&' => 'ov' // Overlap operator for arrays
        ];
        
        return $operatorMap[strtoupper($operator)] ?? 'eq';
    }
    
    /**
     * ORDER BY ifadesini Supabase formatına çevirir
     */
    private function convertOrderBy($orderBy) {
        // Örnek: "sort_order ASC" -> "sort_order.asc"
        // Örnek: "created_at DESC" -> "created_at.desc"
        
        $orderBy = trim($orderBy);
        
        // Birden fazla sıralama varsa virgülle ayır
        if (strpos($orderBy, ',') !== false) {
            $parts = explode(',', $orderBy);
            $converted = [];
            
            foreach ($parts as $part) {
                $converted[] = $this->convertSingleOrderBy(trim($part));
            }
            
            return implode(',', $converted);
        }
        
        return $this->convertSingleOrderBy($orderBy);
    }
    
    /**
     * Tek bir ORDER BY ifadesini çevirir
     */
    private function convertSingleOrderBy($orderBy) {
        $parts = explode(' ', trim($orderBy));
        
        if (count($parts) == 1) {
            // Sadece sütun adı varsa, ASC olarak kabul et
            return $parts[0] . '.asc';
        }
        
        if (count($parts) == 2) {
            $column = $parts[0];
            $direction = strtoupper($parts[1]);
            
            if ($direction === 'ASC') {
                return $column . '.asc';
            } elseif ($direction === 'DESC') {
                return $column . '.desc';
            }
        }
        
        // Varsayılan olarak ASC
        return $parts[0] . '.asc';
    }
    
    /**
     * PHP array'ini PostgreSQL array literal formatına çevirir
     * Örnek: ['value1', 'value2'] -> '{"value1","value2"}'
     */
    private function formatArrayForPostgreSQL($array) {
        if (!is_array($array)) {
            return $array;
        }
        
        // Array elemanlarını quote'la ve virgülle ayır
        $quotedElements = array_map(function($element) {
            // String değerleri quote'la, diğerlerini olduğu gibi bırak
            if (is_string($element)) {
                // PostgreSQL array'inde çift tırnak kullanılır
                return '"' . str_replace('"', '""', $element) . '"';
            }
            return $element;
        }, $array);
        
        // PostgreSQL array literal formatı: {element1,element2}
        return '{' . implode(',', $quotedElements) . '}';
    }
}
