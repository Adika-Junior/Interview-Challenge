#!/bin/bash

# Base URL (update if needed)
BASE_URL="http://127.0.0.1:8000/api"

# Optional: Set your JWT token here
JWT_TOKEN=""

# Helper function to test an endpoint
function test_endpoint() {
  local endpoint="$1"
  local method="${2:-GET}"
  local data="$3"
  local url="$BASE_URL/$endpoint"
  local auth_header=""
  if [[ -n "$JWT_TOKEN" ]]; then
    auth_header="-H 'Authorization: Bearer $JWT_TOKEN'"
  fi
  echo "\n===== Testing: $endpoint ($method) ====="
  if [[ "$method" == "POST" ]]; then
    eval curl -s -w '\nHTTP_STATUS:%{{http_code}}' -X POST $auth_header -d "$data" "$url"
  else
    eval curl -s -w '\nHTTP_STATUS:%{{http_code}}' $auth_header "$url"
  fi
  echo -e "\n==============================\n"
}

# Auth endpoints
test_endpoint "auth/login.php" "POST" "username=demo&password=demo"
test_endpoint "auth/logout.php"
test_endpoint "auth/check.php"

# Admin endpoints
test_endpoint "admin/tasks.php"
test_endpoint "admin/users.php"
test_endpoint "admin/dashboard.php"
test_endpoint "admin/task_comments.php"

# User endpoints
test_endpoint "user/dashboard.php"
test_endpoint "user/task_comments.php"
test_endpoint "user/task_comments_sse.php"
test_endpoint "user/tasks.php"
test_endpoint "user/tasks_sse.php"

# Standalone endpoints
test_endpoint "send_deadline_reminders.php"
test_endpoint "test.php" 