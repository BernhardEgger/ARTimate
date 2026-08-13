<?php
declare(strict_types=1);

// Send cache control headers to prevent caching of the admin console page itself
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$storageRoot = __DIR__ . '/../projects';

// Helper to recursively delete a directory
function deleteDir(string $dirPath): void
{
  if (!is_dir($dirPath)) {
    return;
  }
  $files = array_diff(scandir($dirPath), ['.', '..']);
  foreach ($files as $file) {
    $path = $dirPath . '/' . $file;
    if (is_dir($path)) {
      deleteDir($path);
    } else {
      unlink($path);
    }
  }
  rmdir($dirPath);
}

// Handle project deletion
$message = '';
$messageType = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
  $projectId = $_POST['projectId'] ?? '';
  if (preg_match('/^[a-f0-9]{16}$/', $projectId)) {
    $projectPath = $storageRoot . '/' . $projectId;
    if (is_dir($projectPath)) {
      deleteDir($projectPath);
      $message = "Project {$projectId} has been successfully deleted.";
    } else {
      $message = "Project not found.";
      $messageType = 'error';
    }
  } else {
    $message = "Invalid Project ID.";
    $messageType = 'error';
  }
}

// Scan projects directory
$projects = [];
if (is_dir($storageRoot)) {
  $dirs = array_diff(scandir($storageRoot), ['.', '..']);
  foreach ($dirs as $dir) {
    if (preg_match('/^[a-f0-9]{16}$/', $dir)) {
      $metadataPath = $storageRoot . '/' . $dir . '/metadata.json';
      $metadata = null;
      
      // Try to load metadata.json first
      if (is_file($metadataPath)) {
        $metadataRaw = file_get_contents($metadataPath);
        if ($metadataRaw !== false) {
          $metadata = json_decode($metadataRaw, true);
        }
      }
      
      // Fallback: If metadata.json is missing or corrupt, but index.html exists
      if (!$metadata && is_file($storageRoot . '/' . $dir . '/index.html')) {
        $htmlContent = file_get_contents($storageRoot . '/' . $dir . '/index.html');
        // Extract embeddedProject JSON configuration from script block
        if ($htmlContent !== false && preg_match('/const embeddedProject\s*=\s*(\{.*?\});/s', $htmlContent, $matches)) {
          $decoded = json_decode($matches[1], true);
          if ($decoded) {
            $metadata = $decoded;
          }
        }
        
        // If extraction fails, compile minimal fallback metadata
        if (!$metadata) {
          $metadata = [
            'projectId' => $dir,
            'createdAt' => date('c', filemtime($storageRoot . '/' . $dir . '/index.html')),
            'pairs' => []
          ];
        }
      }
      
      if ($metadata) {
        // Enforce backward compatibility properties for preview grid images/videos
        if (isset($metadata['pairs']) && is_array($metadata['pairs'])) {
          foreach ($metadata['pairs'] as &$pair) {
            $index = $pair['targetIndex'] ?? 0;
            
            if (!isset($pair['imagePath'])) {
              $imagesDir = $storageRoot . '/' . $dir . '/images';
              $matchedFile = 'images/' . $index . '.png'; // Default fallback
              if (is_dir($imagesDir)) {
                $found = glob($imagesDir . '/' . $index . '.*');
                if (!empty($found)) {
                  $matchedFile = 'images/' . basename($found[0]);
                }
              }
              $pair['imagePath'] = $matchedFile;
            }
            
            if (!isset($pair['videoPath'])) {
              $videosDir = $storageRoot . '/' . $dir . '/videos';
              $matchedFile = 'videos/' . $index . '.mp4'; // Default fallback
              if (is_dir($videosDir)) {
                $found = glob($videosDir . '/' . $index . '.*');
                if (!empty($found)) {
                  $matchedFile = 'videos/' . basename($found[0]);
                }
              }
              $pair['videoPath'] = $matchedFile;
            }
          }
        }
        $projects[] = $metadata;
      }
    }
  }
}

// Sort projects by createdAt descending, if available
usort($projects, function ($a, $b) {
  $t1 = isset($a['createdAt']) ? strtotime($a['createdAt']) : 0;
  $t2 = isset($b['createdAt']) ? strtotime($b['createdAt']) : 0;
  return $t2 <=> $t1;
});

// Determine the full host URL dynamically so the QR codes dynamically link correctly
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$appRootPath = str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'])));
$appRootPath = rtrim($appRootPath, '/');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Moderation Dashboard - ARTimate</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      color-scheme: light;
      font-family: system-ui, Arial, sans-serif;
    }
    :root {
      --bg-color: #0b0f19;
      --card-bg: #151c2c;
      --card-border: #243049;
      --text-color: #f3f4f6;
      --text-muted: #9ca3af;
      --primary: #4f46e5;
      --primary-hover: #6366f1;
      --danger: #ef4444;
      --danger-hover: #f87171;
      --success: #10b981;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Outfit', sans-serif;
      background-color: var(--bg-color);
      color: var(--text-color);
      padding: 40px 24px;
      min-height: 100vh;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
    }

    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 32px;
      border-bottom: 1px solid var(--card-border);
      padding-bottom: 20px;
    }

    h1 {
      font-size: 28px;
      font-weight: 700;
      background: linear-gradient(to right, #a5b4fc, #6366f1);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .meta-summary {
      font-size: 14px;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .badge {
      background: var(--primary);
      color: #fff;
      padding: 4px 10px;
      border-radius: 9999px;
      font-weight: 500;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 16px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      cursor: pointer;
      border: none;
      transition: all 0.2s ease;
    }

    .btn-secondary {
      background: var(--card-bg);
      color: var(--text-color);
      border: 1px solid var(--card-border);
    }

    .btn-secondary:hover {
      background: var(--card-border);
    }

    .btn-danger {
      background: var(--danger);
      color: #fff;
    }

    .btn-danger:hover {
      background: var(--danger-hover);
    }

    .btn-primary {
      background: var(--primary);
      color: #fff;
    }

    .btn-primary:hover {
      background: var(--primary-hover);
    }

    .alert {
      padding: 16px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-size: 15px;
      line-height: 1.5;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alert-success {
      background: rgba(16, 185, 129, 0.15);
      border: 1px solid var(--success);
      color: #a7f3d0;
    }

    .alert-error {
      background: rgba(239, 68, 68, 0.15);
      border: 1px solid var(--danger);
      color: #fca5a5;
    }

    .project-list {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .project-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 24px;
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 24px;
      align-items: start;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .project-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
      border-color: #3b4f7a;
    }

    .project-info {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .project-id {
      font-family: monospace;
      font-size: 16px;
      font-weight: 600;
      color: #818cf8;
      word-break: break-all;
    }

    .project-date {
      font-size: 13px;
      color: var(--text-muted);
    }

    .project-actions {
      display: flex;
      gap: 8px;
      margin-top: 12px;
    }

    .qr-container {
      margin-top: 8px;
      background: #fff;
      padding: 8px;
      border-radius: 8px;
      display: inline-flex;
      align-self: flex-start;
    }

    .qr-container img {
      display: block;
      width: 90px;
      height: 90px;
    }

    .target-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
    }

    .target-pair {
      background: rgba(0, 0, 0, 0.2);
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 12px;
      display: flex;
      gap: 12px;
    }

    .media-container {
      width: 50%;
      aspect-ratio: 4/3;
      position: relative;
      border-radius: 8px;
      overflow: hidden;
      background: #000;
      border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .media-container img,
    .media-container video {
      width: 100%;
      height: 100%;
      object-fit: contain;
      display: block;
    }

    .media-label {
      position: absolute;
      bottom: 4px;
      left: 4px;
      background: rgba(0, 0, 0, 0.6);
      color: #fff;
      font-size: 9px;
      padding: 2px 6px;
      border-radius: 4px;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .no-projects {
      text-align: center;
      padding: 64px 24px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      color: var(--text-muted);
    }

    .no-projects h3 {
      color: var(--text-color);
      margin-bottom: 8px;
    }

    /* Modal dialog overrides */
    dialog {
      background: var(--card-bg);
      color: var(--text-color);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 24px;
      margin: auto;
      max-width: 400px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
    }

    dialog::backdrop {
      background: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(4px);
    }

    dialog h3 {
      font-size: 18px;
      margin-bottom: 12px;
    }

    dialog p {
      font-size: 14px;
      color: var(--text-muted);
      margin-bottom: 20px;
      line-height: 1.5;
    }

    dialog .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }
  </style>
</head>

<body>
  <div class="container">
    <header>
      <div>
        <h1>Moderation Dashboard</h1>
        <div class="meta-summary" style="margin-top: 6px;">
          <span>Active projects: <strong class="badge"><?php echo count($projects); ?></strong></span>
        </div>
      </div>
      <a href="../index.php" class="btn btn-secondary">← Back to Builder</a>
    </header>

    <?php if ($message !== ''): ?>
      <div class="alert alert-<?php echo $messageType; ?>">
        <span><?php echo htmlspecialchars($message); ?></span>
      </div>
    <?php endif; ?>

    <?php if (empty($projects)): ?>
      <div class="no-projects">
        <h3>No projects found</h3>
        <p>Go to the Builder page to create one!</p>
      </div>
    <?php else: ?>
      <div class="project-list">
        <?php foreach ($projects as $project): ?>
          <?php
          $projId = htmlspecialchars((string) ($project['projectId'] ?? ''));
          $createdAt = htmlspecialchars((string) ($project['createdAt'] ?? 'Unknown'));
          $formattedDate = $createdAt !== 'Unknown' ? date('F j, Y, g:i a', strtotime($createdAt)) : 'Unknown';
          $pairs = $project['pairs'] ?? [];
          
          // Generate the specific runtime static viewer link and zip link
          $targetOpenUrl = $protocol . $host . $appRootPath . '/projects/' . rawurlencode($projId) . '/index.html';
          $zipUrl = $protocol . $host . $appRootPath . '/projects/' . rawurlencode($projId) . '/' . rawurlencode($projId) . '.zip';
          
          // Append cache-busting timestamp to QR code so scanning always gets a clean target
          $qrApiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($targetOpenUrl . '?t=' . time());
          ?>
          <div class="project-card" id="project-<?php echo $projId; ?>">
            <div class="project-info">
              <div>
                <span
                  style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Project
                  ID</span>
                <div class="project-id"><?php echo $projId; ?></div>
              </div>
              <div>
                <span
                  style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Created
                  At</span>
                <div class="project-date"><?php echo $formattedDate; ?></div>
              </div>
              <div>
                <span
                  style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">Targets</span>
                <div style="font-size: 14px; font-weight: 500; margin-top: 4px;"><?php echo count($pairs); ?> pair(s)</div>
              </div>
              
              <div>
                <span style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);">QR Code</span>
                <div class="qr-container">
                  <img src="<?php echo $qrApiUrl; ?>" alt="QR Code for Project <?php echo $projId; ?>" />
                </div>
              </div>

              <div class="project-actions">
                <a href="<?php echo $targetOpenUrl; ?>" target="_blank" class="btn btn-primary"
                  style="flex: 1;">Open</a>
                <a href="<?php echo $zipUrl; ?>" class="btn btn-secondary"
                  style="flex: 1; text-align: center; border-color: #4f46e5; color: #818cf8;">ZIP</a>
                <button type="button" class="btn btn-danger" onclick="confirmDelete('<?php echo $projId; ?>')"
                  style="flex: 1;">Delete</button>
              </div>
            </div>

            <div class="target-grid">
              <?php foreach ($pairs as $pair): ?>
                <?php
                // Append cache-busting parameter to avoid caching in dashboard previews
                $imagePath = '../projects/' . $projId . '/' . htmlspecialchars((string) ($pair['imagePath'] ?? ''));
                $videoPath = '../projects/' . $projId . '/' . htmlspecialchars((string) ($pair['videoPath'] ?? ''));
                $videoType = htmlspecialchars((string) ($pair['videoType'] ?? 'video/mp4'));
                ?>
                <div class="target-pair">
                  <div class="media-container">
                    <img src="<?php echo $imagePath; ?>" alt="Target image" loading="lazy" />
                    <span class="media-label">Image</span>
                  </div>
                  <div class="media-container">
                    <video autoplay loop muted playsinline preload="metadata">
                      <source src="<?php echo $videoPath; ?>" type="<?php echo $videoType; ?>">
                      Your browser does not support HTML5 video.
                    </video>
                    <span class="media-label">Video</span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <dialog id="deleteDialog">
    <h3>Delete Project?</h3>
    <p>Are you sure you want to delete this project? This will permanently remove all target images, videos, and project
      files from the server. This action cannot be undone.</p>
    <form method="POST" action="index.php">
      <input type="hidden" name="action" value="delete" />
      <input type="hidden" name="projectId" id="deleteProjectId" value="" />
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closeDeleteDialog()">Cancel</button>
        <button type="submit" class="btn btn-danger">Confirm Delete</button>
      </div>
    </form>
  </dialog>

  <script>
    const dialog = document.getElementById('deleteDialog');
    const deleteIdInput = document.getElementById('deleteProjectId');

    function confirmDelete(projectId) {
      deleteIdInput.value = projectId;
      dialog.showModal();
    }

    function closeDeleteDialog() {
      dialog.close();
    }
  </script>
</body>

</html>