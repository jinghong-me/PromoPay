<?php
/**
 * 用户管理类
 */
class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 用户登录
     */
    public function login($username, $password) {
        try {
            $user = $this->db->fetch("SELECT * FROM users WHERE username = ?", [$username]);

            if (!$user) {
                return ['success' => false, 'message' => '用户名或密码错误'];
            }

            // 检查用户状态
            if ($user['status'] != 1) {
                return ['success' => false, 'message' => '账号已被禁用，请联系管理员'];
            }

            if (!password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => '用户名或密码错误'];
            }
            
            // 更新登录信息
            $this->db->update('users', [
                'last_login_at' => date('Y-m-d H:i:s'),
                'last_login_ip' => getClientIP()
            ], 'id = :id', ['id' => $user['id']]);
            
            // 设置会话
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_username'] = $user['username'];
            $_SESSION['user_realname'] = $user['real_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_avatar'] = $user['avatar'];
            
            // 记录操作日志
            logOperation($user['id'], 'login', 'user', $user['id'], ['ip' => getClientIP()]);
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'real_name' => $user['real_name'],
                    'role' => $user['role'],
                    'avatar' => $user['avatar']
                ]
            ];
            
        } catch (Exception $e) {
            logError('Login failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '登录失败，请稍后重试'];
        }
    }
    
    /**
     * 用户登出
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logOperation($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], []);
        }
        session_destroy();
        return ['success' => true];
    }
    
    /**
     * 创建用户
     */
    public function create($data, $createdBy) {
        try {
            // 检查用户名是否已存在
            $existing = $this->db->fetch("SELECT id FROM users WHERE username = ?", [$data['username']]);
            if ($existing) {
                return ['success' => false, 'message' => '用户名已存在'];
            }
            
            $userData = [
                'username' => $data['username'],
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'real_name' => $data['real_name'],
                'role' => $data['role'] ?? 'staff',
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'status' => 1
            ];
            
            $userId = $this->db->insert('users', $userData);
            
            logOperation($createdBy, 'create_user', 'user', $userId, [
                'username' => $data['username'],
                'real_name' => $data['real_name'],
                'role' => $data['role']
            ]);
            
            return ['success' => true, 'id' => $userId];
            
        } catch (Exception $e) {
            logError('Create user failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 更新用户
     */
    public function update($id, $data, $updatedBy) {
        try {
            $updateData = [];
            
            if (isset($data['real_name'])) {
                $updateData['real_name'] = $data['real_name'];
            }
            if (isset($data['phone'])) {
                $updateData['phone'] = $data['phone'];
            }
            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }
            if (isset($data['role'])) {
                $updateData['role'] = $data['role'];
            }
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            if (!empty($data['password'])) {
                $updateData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateData)) {
                return ['success' => false, 'message' => '没有要更新的数据'];
            }
            
            $this->db->update('users', $updateData, 'id = :id', ['id' => $id]);
            
            logOperation($updatedBy, 'update_user', 'user', $id, $updateData);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            logError('Update user failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 删除用户
     */
    public function delete($id, $deletedBy) {
        try {
            // 不能删除自己
            if ($id == $deletedBy) {
                return ['success' => false, 'message' => '不能删除当前登录用户'];
            }
            
            $this->db->delete('users', 'id = :id', ['id' => $id]);
            
            logOperation($deletedBy, 'delete_user', 'user', $id, []);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            logError('Delete user failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 获取用户信息
     */
    public function getById($id) {
        return $this->db->fetch("SELECT id, username, real_name, role, phone, email, avatar, status, 
            last_login_at, last_login_ip, created_at 
            FROM users WHERE id = ?", [$id]);
    }
    
    /**
     * 获取用户列表
     */
    public function getList($filters = [], $page = 1, $perPage = 20) {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['role'])) {
            $where[] = 'role = ?';
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['keyword'])) {
            $where[] = '(username LIKE ? OR real_name LIKE ?)';
            $params[] = '%' . $filters['keyword'] . '%';
            $params[] = '%' . $filters['keyword'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE {$whereClause}", $params)['count'];
        $pagination = paginate($total, $page, $perPage);
        
        $sql = "SELECT id, username, real_name, role, phone, email, avatar, status, 
            last_login_at, created_at 
            FROM users 
            WHERE {$whereClause}
            ORDER BY created_at DESC
            LIMIT {$pagination['offset']}, {$pagination['per_page']}";
        
        $list = $this->db->fetchAll($sql, $params);
        
        return [
            'list' => $list,
            'pagination' => $pagination
        ];
    }
    
    /**
     * 修改密码
     */
    public function changePassword($id, $oldPassword, $newPassword) {
        try {
            $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$id]);
            
            if (!$user) {
                return ['success' => false, 'message' => '用户不存在'];
            }
            
            if (!password_verify($oldPassword, $user['password'])) {
                return ['success' => false, 'message' => '原密码错误'];
            }
            
            $this->db->update('users', [
                'password' => password_hash($newPassword, PASSWORD_DEFAULT)
            ], 'id = :id', ['id' => $id]);
            
            logOperation($id, 'change_password', 'user', $id, []);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            logError('Change password failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 获取当前登录用户
     */
    public static function getCurrentUser() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['user_username'] ?? '',
            'real_name' => $_SESSION['user_realname'] ?? '',
            'role' => $_SESSION['user_role'] ?? '',
            'avatar' => $_SESSION['user_avatar'] ?? ''
        ];
    }
    
    /**
     * 检查是否是管理员
     */
    public static function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}
