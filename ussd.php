<?php
header("Content-Type: text/plain");

// Database config
$host = "localhost";
$user = "root";
$password = "";
$database = "blog_system1";
$mysqli = new mysqli($host, $user, $password, $database);
if ($mysqli->connect_error) {
    die("END Failed to connect: " . $mysqli->connect_error);
}

// USSD input
$sessionId = $_POST["sessionId"] ?? '';
$serviceCode = $_POST["serviceCode"] ?? '';
$phoneNumber = $_POST["phoneNumber"] ?? '';
$text = $_POST["text"] ?? '';
$parts = explode("*", $text);
$level = count($parts);
$response = "";

// Helper functions
function getSessionData($mysqli, $sessionId) {
    $stmt = $mysqli->prepare("SELECT menu_state FROM ussd_sessions WHERE session_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $stmt->bind_result($menu_state);
    if ($stmt->fetch()) {
        return json_decode($menu_state, true);
    }
    return [];
}

function saveSessionData($mysqli, $sessionId, $phoneNumber, $data) {
    $menu_state = json_encode($data);
    $stmt = $mysqli->prepare("INSERT INTO ussd_sessions (session_id, phone_number, menu_state) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $sessionId, $phoneNumber, $menu_state);
    $stmt->execute();
}

// Get current session state
$session = getSessionData($mysqli, $sessionId);

// Main menu
switch ($parts[0]) {
    case "":
        $response .= "CON Welcome to the Blog USSD\n";
        $response .= "1. View Latest Posts\n";
        $response .= "2. Submit a Post\n";
        $response .= "3. Register as Author\n";
        $response .= "4. View Submitted Posts\n";
        $response .= "5. View Profile\n";
        $response .= "6. Switch Author";
        break;

    // 1. View Posts
    case "1":
        if ($level == 1) {
            $sql = "SELECT id, title FROM posts ORDER BY created_at DESC LIMIT 10";
            $result = $mysqli->query($sql);
            if ($result->num_rows > 0) {
                $response .= "CON Latest Posts:\n";
                $posts = [];
                $index = 1;
                while ($row = $result->fetch_assoc()) {
                    $posts[] = $row['id'];
                    $response .= $index . ". " . $row['title'] . "\n";
                    $index++;
                }
                $session['posts'] = $posts;
                saveSessionData($mysqli, $sessionId, $phoneNumber, $session);
                $response .= "Reply with post number to read more:";
            } else {
                $response .= "END No posts available.";
            }
        } elseif ($level == 2) {
            $selection = intval($parts[1]);
            if (isset($session['posts'][$selection - 1])) {
                $post_id = $session['posts'][$selection - 1];
                $sql = "SELECT posts.title, posts.body, users.name as author 
                        FROM posts 
                        LEFT JOIN users ON posts.user_id = users.id 
                        WHERE posts.id = ?";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("i", $post_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $author = $row['author'] ?? 'Unknown';
                    $response .= "END " . $row['title'] . "\nBy: " . $author . "\n" . $row['body'];
                } else {
                    $response .= "END Post not found.";
                }
            } else {
                $response .= "END Invalid post selection.";
            }
        }
        break;

    // 2. Submit a Post
    case "2":
        if (!isset($session['author_id'])) {
            $response .= "END You must switch to an author first.";
        } elseif ($level == 1) {
            $response .= "CON Enter post title:";
        } elseif ($level == 2) {
            $session['post_title'] = $parts[1];
            saveSessionData($mysqli, $sessionId, $phoneNumber, $session);
            $response .= "CON Enter post content:";
        } elseif ($level == 3) {
            $title = $session['post_title'] ?? '';
            $body = $parts[2];
            $user_id = $session['author_id'];
            $stmt = $mysqli->prepare("INSERT INTO posts (user_id, title, body) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $title, $body);
            if ($stmt->execute()) {
                $response .= "END Post submitted successfully.";
            } else {
                $response .= "END Failed to submit post.";
            }
        }
        break;

    // 3. Register as Author
    case "3":
        if ($level == 1) {
            $response .= "CON Enter your name:";
        } elseif ($level == 2) {
            $session['reg_name'] = $parts[1];
            saveSessionData($mysqli, $sessionId, $phoneNumber, $session);
            $response .= "CON Enter your email:";
        } elseif ($level == 3) {
            $name = $session['reg_name'];
            $email = $parts[2];
            $stmt = $mysqli->prepare("INSERT INTO users (name, email, phone) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $email, $phoneNumber);
            if ($stmt->execute()) {
                $response .= "END Registration successful.";
            } else {
                $response .= "END Registration failed. You might already be registered.";
            }
        }
        break;

    // 4. View Submitted Posts
    case "4":
        if (!isset($session['author_id'])) {
            $response .= "END You must switch to an author first.";
        } elseif ($level == 1) {
            $sql = "SELECT id, title FROM posts WHERE user_id = ? ORDER BY created_at DESC";
            $stmt = $mysqli->prepare($sql);
            $stmt->bind_param("i", $session['author_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $response .= "CON Your Posts:\n";
                $posts = [];
                $index = 1;
                while ($row = $result->fetch_assoc()) {
                    $posts[] = $row['id'];
                    $response .= $index . ". " . $row['title'] . "\n";
                    $index++;
                }
                $session['my_posts'] = $posts;
                saveSessionData($mysqli, $sessionId, $phoneNumber, $session);
                $response .= "Reply with post number to view.";
            } else {
                $response .= "END You have no posts.";
            }
        } elseif ($level == 2) {
            $index = intval($parts[1]) - 1;
            if (isset($session['my_posts'][$index])) {
                $post_id = $session['my_posts'][$index];
                $stmt = $mysqli->prepare("SELECT title, body FROM posts WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $post_id, $session['author_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $response .= "END Title: " . $row['title'] . "\nContent: " . $row['body'];
                } else {
                    $response .= "END Post not found.";
                }
            } else {
                $response .= "END Invalid selection.";
            }
        }
        break;

    // 5. View Profile
    case "5":
        if (!isset($session['author_id'])) {
            $response .= "END You must switch to an author first.";
        } else {
            $stmt = $mysqli->prepare("SELECT name, email, phone FROM users WHERE id = ?");
            $stmt->bind_param("i", $session['author_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $response .= "END Name: " . $row['name'] . "\nEmail: " . $row['email'] . "\nPhone: " . $row['phone'];
            } else {
                $response .= "END Author not found.";
            }
        }
        break;

    // 6. Switch Author
    case "6":
        if ($level == 1) {
            $response .= "CON Enter author name to switch:";
        } elseif ($level == 2) {
            $author_name = $parts[1];
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE name = ?");
            $stmt->bind_param("s", $author_name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $session['author_id'] = $row['id'];
                $session['author_name'] = $author_name;
                saveSessionData($mysqli, $sessionId, $phoneNumber, $session);
                $response .= "END Author switched to " . $author_name;
            } else {
                $response .= "END Author not found.";
            }
        }
        break;

    default:
        $response .= "END Invalid option.";
        break;
}

echo $response;
?>
