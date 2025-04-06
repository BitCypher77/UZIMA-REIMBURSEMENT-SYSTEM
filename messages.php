<?php
require 'config.php';
requireLogin();

// Log user activity
logUserActivity($_SESSION['user_id'], 'page_view', 'Viewed messages');

// Set up variables
$conversations = [];
$messages = [];
$currentConversation = null;
$recipients = [];

// Get all available users for new message
try {
    $stmt = $pdo->prepare("
        SELECT userID, fullName, email 
        FROM users 
        WHERE userID != ? 
        ORDER BY fullName
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $availableUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $errorMessage = "Could not load users list. Please try again.";
}

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid form submission. Please try again.";
    } else {
        $recipientId = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
        $messageContent = isset($_POST['message']) ? trim($_POST['message']) : '';
        
        if (empty($messageContent)) {
            $errorMessage = "Message cannot be empty.";
        } elseif ($recipientId <= 0) {
            $errorMessage = "Please select a recipient.";
        } else {
            try {
                // Check if conversation exists
                $stmt = $pdo->prepare("
                    SELECT conversation_id FROM conversations
                    WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
                    LIMIT 1
                ");
                $stmt->execute([$_SESSION['user_id'], $recipientId, $recipientId, $_SESSION['user_id']]);
                $existingConversation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existingConversation) {
                    $conversationId = $existingConversation['conversation_id'];
                } else {
                    // Create new conversation
                    $stmt = $pdo->prepare("
                        INSERT INTO conversations (user1_id, user2_id, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    $stmt->execute([$_SESSION['user_id'], $recipientId]);
                    $conversationId = $pdo->lastInsertId();
                }
                
                // Insert message
                $stmt = $pdo->prepare("
                    INSERT INTO messages (conversation_id, sender_id, message, sent_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$conversationId, $_SESSION['user_id'], $messageContent]);
                
                // Redirect to view the conversation
                header("Location: messages.php?conversation=" . $conversationId);
                exit;
                
            } catch (PDOException $e) {
                error_log("Error sending message: " . $e->getMessage());
                $errorMessage = "Could not send message. Please try again.";
            }
        }
    }
}

// Fetch user's conversations
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.conversation_id,
            c.last_message_at,
            IF(c.user1_id = ?, c.user2_id, c.user1_id) as other_user_id,
            u.fullName as other_user_name,
            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND recipient_id = ? AND is_read = 0) as unread_count
        FROM conversations c
        JOIN users u ON IF(c.user1_id = ?, c.user2_id, c.user1_id) = u.userID
        WHERE c.user1_id = ? OR c.user2_id = ?
        ORDER BY c.last_message_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $conversations = $stmt->fetchAll();
    
    // Get current conversation if specified
    if (isset($_GET['conversation']) && is_numeric($_GET['conversation'])) {
        $currentConversation = intval($_GET['conversation']);
        
        // Verify user is part of this conversation
        $stmt = $pdo->prepare("
            SELECT conversation_id 
            FROM conversations 
            WHERE conversation_id = ? AND (user1_id = ? OR user2_id = ?)
        ");
        $stmt->execute([$currentConversation, $_SESSION['user_id'], $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            // Invalid conversation ID or user not authorized
            $errorMessage = "Conversation not found.";
            $currentConversation = null;
        } else {
            // Get conversation messages
            $stmt = $pdo->prepare("
                SELECT 
                    m.message_id,
                    m.sender_id,
                    m.message,
                    m.sent_at,
                    m.is_read,
                    u.fullName as sender_name
                FROM messages m
                JOIN users u ON m.sender_id = u.userID
                WHERE m.conversation_id = ?
                ORDER BY m.sent_at ASC
            ");
            $stmt->execute([$currentConversation]);
            $messages = $stmt->fetchAll();
            
            // Mark messages as read
            $stmt = $pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE conversation_id = ? AND sender_id != ? AND is_read = 0
            ");
            $stmt->execute([$currentConversation, $_SESSION['user_id']]);
            
            // Get conversation participants
            $stmt = $pdo->prepare("
                SELECT 
                    IF(c.user1_id = ?, c.user2_id, c.user1_id) as recipient_id,
                    u.fullName as recipient_name
                FROM conversations c
                JOIN users u ON IF(c.user1_id = ?, c.user2_id, c.user1_id) = u.userID
                WHERE c.conversation_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $currentConversation]);
            $recipients = $stmt->fetch();
        }
    }
    
} catch (PDOException $e) {
    error_log("Error fetching conversations: " . $e->getMessage());
    $errorMessage = "Could not load conversations. Please try again.";
}

// Include header
include 'includes/header.php';
?>

<div class="px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Messages</h1>
    </div>

    <?php if (isset($errorMessage)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= $errorMessage ?>
        </div>
    <?php endif; ?>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="flex h-[calc(100vh-220px)] min-h-[400px]">
            <!-- Sidebar -->
            <div class="w-1/3 border-r border-gray-200 dark:border-gray-700 overflow-y-auto">
                <!-- New Message Button -->
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <button id="newMessageBtn" class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <i data-lucide="edit" class="h-5 w-5 mr-2"></i>
                        New Message
                    </button>
                </div>
                
                <!-- Conversations List -->
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($conversations)): ?>
                        <li class="p-4 text-center text-gray-500 dark:text-gray-400">
                            No conversations yet
                        </li>
                    <?php else: ?>
                        <?php foreach ($conversations as $conversation): ?>
                            <li>
                                <a href="messages.php?conversation=<?= $conversation['conversation_id'] ?>" 
                                   class="block p-4 hover:bg-gray-50 dark:hover:bg-gray-700 <?= ($currentConversation == $conversation['conversation_id']) ? 'bg-blue-50 dark:bg-blue-900/20' : '' ?>">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            <?= htmlspecialchars($conversation['other_user_name']) ?>
                                        </span>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                                                <?= $conversation['unread_count'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        <?= date('M j, Y g:i A', strtotime($conversation['last_message_at'])) ?>
                                    </p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Message Content -->
            <div class="w-2/3 flex flex-col h-full">
                <?php if ($currentConversation): ?>
                    <!-- Conversation Header -->
                    <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex items-center">
                        <span class="font-medium text-gray-900 dark:text-white">
                            <?= htmlspecialchars($recipients['recipient_name']) ?>
                        </span>
                    </div>
                    
                    <!-- Messages -->
                    <div class="flex-1 p-4 overflow-y-auto" id="messageContainer">
                        <?php foreach ($messages as $message): ?>
                            <div class="mb-4 <?= ($message['sender_id'] == $_SESSION['user_id']) ? 'text-right' : '' ?>">
                                <div class="inline-block max-w-3/4 px-4 py-2 rounded-lg <?= ($message['sender_id'] == $_SESSION['user_id']) ? 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200' : 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200' ?>">
                                    <p><?= nl2br(htmlspecialchars($message['message'])) ?></p>
                                </div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <?= date('M j, Y g:i A', strtotime($message['sent_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Message Input -->
                    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
                        <form action="messages.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="recipient_id" value="<?= $recipients['recipient_id'] ?>">
                            
                            <div class="flex">
                                <textarea name="message" rows="2" 
                                          class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                          placeholder="Type your message"></textarea>
                                <button type="submit" 
                                        class="ml-3 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i data-lucide="send" class="h-5 w-5"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- No Conversation Selected -->
                    <div class="flex-1 flex flex-col items-center justify-center p-4 text-center">
                        <i data-lucide="message-square" class="h-12 w-12 text-gray-400 dark:text-gray-600 mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">No conversation selected</h3>
                        <p class="mt-1 text-gray-500 dark:text-gray-400">
                            Select a conversation from the sidebar or start a new message
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- New Message Modal -->
<div id="newMessageModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center p-4 z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                New Message
            </h3>
            
            <form action="messages.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="send_message">
                
                <div class="mb-4">
                    <label for="recipient_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Recipient
                    </label>
                    <select id="recipient_id" name="recipient_id" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 dark:border-gray-600 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md dark:bg-gray-700 dark:text-white">
                        <option value="">Select a recipient</option>
                        <?php foreach ($availableUsers as $user): ?>
                            <option value="<?= $user['userID'] ?>"><?= htmlspecialchars($user['fullName']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Message
                    </label>
                    <textarea id="message" name="message" rows="4" 
                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                              placeholder="Type your message"></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="button" id="cancelMessage" 
                            class="mr-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Initialize Lucide icons
    lucide.createIcons();
    
    // New Message Modal
    const newMessageBtn = document.getElementById('newMessageBtn');
    const newMessageModal = document.getElementById('newMessageModal');
    const cancelMessage = document.getElementById('cancelMessage');
    
    if (newMessageBtn && newMessageModal && cancelMessage) {
        newMessageBtn.addEventListener('click', () => {
            newMessageModal.classList.remove('hidden');
        });
        
        cancelMessage.addEventListener('click', () => {
            newMessageModal.classList.add('hidden');
        });
    }
    
    // Scroll to bottom of message container
    const messageContainer = document.getElementById('messageContainer');
    if (messageContainer) {
        messageContainer.scrollTop = messageContainer.scrollHeight;
    }
</script>

<?php include 'includes/footer.php'; ?> 