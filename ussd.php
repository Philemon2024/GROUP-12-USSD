<?php
header("Content-Type: text/plain");

// Database configuration
$host = "localhost";
$user = "root";
$password = "";
$database = "blog_system1";

// Create connection
$mysqli = new mysqli($host, $user, $password, $database);

// Check connection
if ($mysqli->connect_error) {
    die("END Failed to connect: " . $mysqli->connect_error);
}

// Read USSD variables
$sessionId = $_POST["sessionId"] ?? '';
$serviceCode = $_POST["serviceCode"] ?? '';
$phoneNumber = $_POST["phoneNumber"] ?? '';
$text = $_POST["text"] ?? '';

// Split text into parts
$parts = explode("*", $text);
$level = count($parts);

$response = "";

// Helper function to manage session data
function getSessionData($mysqli, $sessionId) {
    $stmt = $mysqli->prepare("SELECT menu_state FROM ussd_sessions WHERE session_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $stmt->bind_result($menu_state);
    if ($stmt->fetch()) {
        return json_decode($menu_state, true);
    }
    return null;
}

function saveSessionData($mysqli, $sessionId, $phoneNumber, $data) {
    $menu_state = json_encode($data);
    $stmt = $mysqli->prepare("INSERT INTO ussd_sessions (session_id, phone_number, menu_state) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $sessionId, $phoneNumber, $menu_state);
    $stmt->execute();
}

// Main menu logic
switch ($parts[0]) {
    case "":
        $response .= "CON Welcome to the Blog USSD\n";
        $response .= "1. View Latest Posts\n";
        $response .= "2. Submit a Post\n";
        $response .= "3. Register as Author\n";
        $response .= "4. View Submitted Posts\n"; // Option to view submitted posts
        $response .= "5. View Profile\n"; // New option to view profile
        break;

    case "5": // View Profile
        if ($level == 1) {
            // Fetch user details based on the phone number
            $sql = "SELECT name, email, phone FROM users WHERE phone = '$phoneNumber' LIMIT 1";
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $response .= "CON Your Profile:\n";
                $response .= "Name: " . $user['name'] . "\n";
                $response .= "Email: " . $user['email'] . "\n";
                $response .= "Phone: " . $user['phone'] . "\n";
                $response .= "Reply with 1 to go back to the main menu.";
            } else {
                $response .= "END No profile found. Please register first.";
            }
        } elseif ($level == 2) {
            $response .= "CON Returning to main menu...";
            // Here we would redirect to the main menu or do other actions.
        }
        break;

    case "1": // View Latest Posts
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

                // Save post IDs to session
                saveSessionData($mysqli, $sessionId, $phoneNumber, ['posts' => $posts]);

                $response .= "Reply with post number to read more:";
            } else {
                $response .= "END No posts available.";
            }
        } elseif ($level == 2) {
            $selection = intval($parts[1]);
            $session = getSessionData($mysqli, $sessionId);

            if ($session && isset($session['posts'][$selection - 1])) {
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

    case "4": // View Submitted Posts
        if ($level == 1) {
            // Fetch posts by the logged-in user (author)
            $sql = "SELECT id, title FROM posts WHERE user_id = (SELECT id FROM users WHERE phone = '$phoneNumber') ORDER BY created_at DESC";
            $result = $mysqli->query($sql);

            if ($result->num_rows > 0) {
                $response .= "CON Your Submitted Posts:\n";
                $posts = [];
                $index = 1;
                while ($row = $result->fetch_assoc()) {
                    $posts[] = $row['id'];
                    $response .= $index . ". " . $row['title'] . "\n";
                    $index++;
                }

                // Save post IDs to session
                saveSessionData($mysqli, $sessionId, $phoneNumber, ['submitted_posts' => $posts]);

                $response .= "Reply with post number to view, edit, or delete:";
            } else {
                $response .= "END You have not submitted any posts yet.";
            }
        } elseif ($level == 2) {
            $selection = intval($parts[1]);
            $session = getSessionData($mysqli, $sessionId);

            if ($session && isset($session['submitted_posts'][$selection - 1])) {
                $post_id = $session['submitted_posts'][$selection - 1];
                $sql = "SELECT posts.title, posts.body FROM posts WHERE id = ? AND user_id = (SELECT id FROM users WHERE phone = '$phoneNumber')";
                $stmt = $mysqli->prepare($sql);
                $stmt->bind_param("i", $post_id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $response .= "CON View/Edit/Delete Post:\n";
                    $response .= "1. Edit Title\n";
                    $response .= "2. Edit Body\n";
                    $response .= "3. Delete Post\n"; // Option to delete
                    $response .= "4. Cancel\n";
                    saveSessionData($mysqli, $sessionId, $phoneNumber, ['edit_post_id' => $post_id, 'post_title' => $row['title'], 'post_body' => $row['body']]);
                } else {
                    $response .= "END You are not the author of this post.";
                }
            } else {
                $response .= "END Invalid post selection.";
            }
        }
        break;

    default:
        $response .= "END Invalid option.";
        break;
}

echo $response;
?>
