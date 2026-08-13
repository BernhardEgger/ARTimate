<?php
declare(strict_types=1);

// Send cache control headers to prevent caching of the builder page itself
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$storageRoot = __DIR__ . '/projects';

function safeBasename(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? 'file';
    return $name !== '' ? $name : 'file';
}

function requireUploadedFile(string $field): array
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        throw new RuntimeException("Missing upload: {$field}");
    }

    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Upload failed for {$field}.");
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException("Invalid uploaded file: {$field}");
    }

    return $file;
}

function moveUploadedFile(array $file, string $destination): void
{
    if (!@move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }
}

function projectDir(string $storageRoot, string $projectId): string
{
    return $storageRoot . '/' . $projectId;
}

function textResponse(string $message, int $status)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

function showErrorPage(string $message)
{
    ?>
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8" />
      <title>Error - ARTimate</title>
      <style>
        body { font-family: system-ui, Arial, sans-serif; margin: 40px; background: #fff; color: #111; }
        .error-card { border: 1px solid #b00020; border-radius: 12px; padding: 24px; max-width: 600px; margin: 0 auto; }
        h1 { color: #b00020; margin-top: 0; }
        p { line-height: 1.5; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-size: 14px; }
        a { display: inline-block; margin-top: 16px; color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
      </style>
    </head>
    <body>
      <div class="error-card">
        <h1>Configuration / Error</h1>
        <p><?php echo $message; ?></p>
        <a href="index.php">Go back to builder</a>
      </div>
    </body>
    </html>
    <?php
    exit;
}

function showSuccessPage(string $projectId, string $projectUrl, string $zipUrl)
{
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($projectUrl);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="utf-8" />
      <title>Project Created - ARTimate</title>
      <style>
        body { font-family: system-ui, Arial, sans-serif; margin: 40px; background: #fafafa; color: #111; display: flex; align-items: center; justify-content: center; min-height: 80vh; }
        .success-card { background: #fff; border: 1px solid #dadce0; border-radius: 16px; padding: 32px; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .icon { font-size: 48px; color: #137333; margin-bottom: 16px; }
        h1 { font-size: 24px; margin: 0 0 8px 0; color: #137333; }
        p { line-height: 1.5; color: #5f6368; margin: 0 0 24px 0; }
        .url-box { background: #f1f3f4; border-radius: 8px; padding: 12px; font-family: monospace; font-size: 14px; word-break: break-all; margin-bottom: 24px; border: 1px solid #e0e0e0; }
        .url-box a { color: #0066cc; text-decoration: none; }
        .url-box a:hover { text-decoration: underline; }
        .qr-code { margin-bottom: 24px; }
        .qr-code img { border: 1px solid #ddd; padding: 8px; border-radius: 8px; background: #fff; }
        .actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn { display: inline-block; padding: 12px 20px; border-radius: 8px; font-weight: 500; text-decoration: none; font-size: 14px; cursor: pointer; transition: background 0.2s, border-color 0.2s; }
        .btn.primary { background: #111; color: #fff; border: 1px solid #111; }
        .btn.primary:hover { background: #222; }
        .btn.secondary { background: #fff; color: #3c4043; border: 1px solid #dadce0; }
        .btn.secondary:hover { background: #f8f9fa; }
        .btn.download { background: #e8f0fe; color: #1a73e8; border: 1px solid #d2e3fc; }
        .btn.download:hover { background: #d2e3fc; }
      </style>
    </head>
    <body>
      <div class="success-card">
        <div class="icon">✓</div>
        <h1>Project Generated!</h1>
        <p>Your AR project has been created successfully. Scan the QR code below to view it on your mobile device:</p>
        
        <div class="url-box">
          <a href="<?php echo htmlspecialchars($projectUrl); ?>" target="_blank"><?php echo htmlspecialchars($projectUrl); ?></a>
        </div>
        
        <div class="qr-code">
          <img src="<?php echo htmlspecialchars($qrUrl); ?>" alt="Project QR Code" width="220" height="220" />
        </div>
        
        <div class="actions">
          <a href="index.php" class="btn secondary">Back to Builder</a>
          <a href="<?php echo htmlspecialchars($zipUrl); ?>" class="btn download">Download ZIP</a>
          <a href="<?php echo htmlspecialchars($projectUrl); ?>" class="btn primary" target="_blank">Open Viewer</a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $maxSize = ini_get('post_max_size');
    showErrorPage("The uploaded files exceed the server's limit (post_max_size = {$maxSize}). Please upload smaller files.");
}

function addFolderToZip(string $dir, ZipArchive $zip, int $exclusiveLength)
{
    $handle = opendir($dir);
    if ($handle === false) {
        return;
    }
    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $filePath = $dir . '/' . $file;
        if (is_dir($filePath)) {
            addFolderToZip($filePath, $zip, $exclusiveLength);
        } else {
            // Exclude the zip file itself
            if (pathinfo($filePath, PATHINFO_EXTENSION) === 'zip') {
                continue;
            }
            $localPath = substr($filePath, $exclusiveLength);
            $localPath = str_replace('\\', '/', $localPath);
            $zip->addFile($filePath, $localPath);
        }
    }
    closedir($handle);
}

function createProjectZip(string $projectDir, string $projectId): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('The PHP ZipArchive extension is not enabled. Cannot create ZIP archive.');
    }

    $zipPath = $projectDir . '/' . $projectId . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Failed to create ZIP archive.");
    }

    $realProjectDir = realpath($projectDir);
    if ($realProjectDir === false) {
        throw new RuntimeException("Failed to locate project directory.");
    }

    $exclusiveLength = strlen($realProjectDir) + 1;
    addFolderToZip($realProjectDir, $zip, $exclusiveLength);

    $zip->close();
    return $zipPath;
}

function handleSaveProject(string $storageRoot)
{
    $pairsRaw = $_POST['pairs'] ?? '';
    if (!is_string($pairsRaw) || $pairsRaw === '') {
        showErrorPage('Missing project metadata.');
    }

    $pairs = json_decode($pairsRaw, true);
    if ($pairs === null && json_last_error() !== JSON_ERROR_NONE) {
        showErrorPage('Invalid project metadata.');
    }

    if (!is_array($pairs) || $pairs === []) {
        showErrorPage('Project must contain at least one pair.');
    }

    try {
        $targetsMindBase64 = $_POST['targetsMindBase64'] ?? '';
        if ($targetsMindBase64 === '') {
            throw new RuntimeException('Missing targets.mind compiled data.');
        }
        $targetsMindDecoded = base64_decode($targetsMindBase64, true);
        if ($targetsMindDecoded === false) {
            throw new RuntimeException('Invalid targets.mind encoding.');
        }

        $projectId = bin2hex(random_bytes(8));
        $projectDir = projectDir($storageRoot, $projectId);
        $imagesDir = $projectDir . '/images';
        $videosDir = $projectDir . '/videos';

        if (!@mkdir($imagesDir, 0775, true) && !is_dir($imagesDir)) {
            throw new RuntimeException('Failed to create images directory.');
        }
        if (!@mkdir($videosDir, 0775, true) && !is_dir($videosDir)) {
            throw new RuntimeException('Failed to create videos directory.');
        }

        if (@file_put_contents($projectDir . '/targets.mind', $targetsMindDecoded) === false) {
            throw new RuntimeException('Failed to save targets.mind file.');
        }

        $savedPairs = [];
        foreach ($pairs as $index => $pair) {
            if (!is_array($pair)) {
                throw new RuntimeException('Invalid pair metadata.');
            }

            $imageFile = requireUploadedFile("image_{$index}");
            $videoFile = requireUploadedFile("video_{$index}");

            $imageName = safeBasename((string) ($pair['imageName'] ?? $imageFile['name']));
            $videoName = safeBasename((string) ($pair['videoName'] ?? $videoFile['name']));
            $imageExt = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
            $videoExt = strtolower(pathinfo($videoName, PATHINFO_EXTENSION));

            $imageStoredName = $index . ($imageExt !== '' ? ".{$imageExt}" : '');
            $videoStoredName = $index . ($videoExt !== '' ? ".{$videoExt}" : '');

            moveUploadedFile($imageFile, $imagesDir . '/' . $imageStoredName);
            moveUploadedFile($videoFile, $videosDir . '/' . $videoStoredName);

	$savedPairs[] = [
                'targetIndex' => $index,
                'imageName' => $imageName,
                'imagePath' => 'images/' . $imageStoredName,
                'videoName' => $videoName,
                'videoPath' => 'videos/' . $videoStoredName,
                'videoType' => (string) ($pair['videoType'] ?? $videoFile['type'] ?? 'video/mp4'),
                'videoUrl' => 'videos/' . $videoStoredName,
                'width' => isset($pair['width']) ? (int)$pair['width'] : null,
                'height' => isset($pair['height']) ? (int)$pair['height'] : null,
            ];
        }

        // Copy the local script libraries to the project directory to make it fully standalone
        if (!@copy(__DIR__ . '/aframe_1.5.0.min.js', $projectDir . '/aframe_1.5.0.min.js')) {
            throw new RuntimeException('Failed to copy aframe_1.5.0.min.js script.');
        }
        if (!@copy(__DIR__ . '/mindar-image-aframe_1.2.5.prod.js', $projectDir . '/mindar-image-aframe_1.2.5.prod.js')) {
            throw new RuntimeException('Failed to copy mindar-image-aframe_1.2.5.prod.js script.');
        }

        $embeddedProjectJson = json_encode([
            'projectId' => $projectId,
            'createdAt' => gmdate('c'),
            'targetsUrl' => 'targets.mind',
            'pairs' => $savedPairs,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($embeddedProjectJson === false) {
            throw new RuntimeException('Failed to encode project metadata.');
        }

        // Write metadata.json to disk so the admin control panel can scan and list the projects
        if (@file_put_contents($projectDir . '/metadata.json', $embeddedProjectJson) === false) {
            throw new RuntimeException('Failed to save metadata.json file.');
        }

        // Generate the standalone static viewer index.html in the project folder with cache-busting headers
        $viewerTemplate = <<<'HTML'
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="0" />
  <title>ARTimate Viewer</title>
  <script src="aframe_1.5.0.min.js"></script>
  <script src="mindar-image-aframe_1.2.5.prod.js"></script>
  <style>
    :root {
      color-scheme: light;
      font-family: system-ui, Arial, sans-serif;
    }
    * { box-sizing: border-box; }
    html, body {
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 0;
    }
    body.viewer-mode {
      overflow: hidden;
    }
    .hidden { display: none !important; }
    .msg {
      position: fixed;
      top: 12px;
      left: 12px;
      right: 12px;
      z-index: 99999;
      background: rgba(0, 0, 0, 0.6);
      color: #fff;
      padding: 10px 12px;
      border-radius: 10px;
    }
    .a-enter-vr-button { display: none !important; }
  </style>
</head>
<body class="viewer-mode">
  <div id="message" class="msg hidden"></div>

  <script>
    const embeddedProject = 
HTML;

        $viewerTemplate .= $embeddedProjectJson;

        $viewerTemplate .= <<<'HTML'
;

    function showMessage(msg) {
      const el = document.getElementById("message");
      if (el) { el.textContent = msg; el.classList.remove("hidden"); }
    }

    function setupVideoEvents(scene) {
      scene.addEventListener("loaded", () => {
        document.querySelectorAll("video").forEach((vid) => {
          vid.play().catch(() => {
            const resume = () => { vid.play(); document.body.removeEventListener("touchstart", resume); };
            document.body.addEventListener("touchstart", resume);
          });
          const aVideo = document.querySelector(`a-video[src="#${vid.id}"]`);
          if (!aVideo) return;
          const target = aVideo.parentElement;
          target.addEventListener("targetFound", () => { vid.currentTime = 0; vid.play(); });
          target.addEventListener("targetLost", () => { vid.pause(); });
        });
      });
    }

    function buildScene(project) {
      const pairs = project.pairs || [];
      const scene = document.createElement("a-scene");
      // Cache bust targets.mind with timestamp
      scene.setAttribute("mindar-image", `imageTargetSrc: ${project.targetsUrl}?t=${Date.now()}; maxTrack: ${pairs.length}`);
      scene.setAttribute("color-space", "sRGB");
      scene.setAttribute("renderer", "colorManagement: true, physicallyCorrectLights");
      scene.setAttribute("vr-mode-ui", "enabled: false");
      scene.setAttribute("device-orientation-permission-ui", "enabled: false");

      const assets = document.createElement("a-assets");
      scene.appendChild(assets);

      const camera = document.createElement("a-camera");
      camera.setAttribute("position", "0 0 0");
      camera.setAttribute("look-controls", "enabled: false");
      scene.appendChild(camera);

      pairs.forEach((pair, idx) => {
        const target = document.createElement("a-entity");
        target.setAttribute("mindar-image-target", `targetIndex: ${idx}`);

        const videoId = `vid_${idx}`;
        const vid = document.createElement("video");
        vid.setAttribute("id", videoId);
        // Cache bust video file with timestamp
        vid.setAttribute("src", pair.videoUrl + "?t=" + Date.now());
        vid.setAttribute("response-type", "arraybuffer");
        vid.setAttribute("crossorigin", "anonymous");
        vid.setAttribute("webkit-playsinline", "");
        vid.setAttribute("playsinline", "");
        vid.setAttribute("autoplay", "");
        vid.setAttribute("muted", "");
        vid.setAttribute("loop", "");
        
        assets.appendChild(vid);

        const aVideo = document.createElement("a-video");
        aVideo.setAttribute("src", `#${videoId}`);
        aVideo.setAttribute("position", "0 0 0");
     
        // Dynamically calculate aspect ratio from metadata properties
        const aspectRatio = pair.width && pair.height ? (pair.height / pair.width) : 1;
        aVideo.setAttribute("width", "1");
        aVideo.setAttribute("height", aspectRatio);
        
        target.appendChild(aVideo);

        scene.appendChild(target);
      });

      document.body.appendChild(scene);
      setupVideoEvents(scene);
    }

    function startViewer() {
      try {
        if (!embeddedProject) throw new Error("Metadata missing.");
        buildScene(embeddedProject);
      } catch (err) {
        showMessage(err.message);
      }
    }

    startViewer();
  </script>
</body>
</html>
HTML;

        if (@file_put_contents($projectDir . '/index.html', $viewerTemplate) === false) {
            throw new RuntimeException('Failed to write viewer index.html file.');
        }

        // Create Zip file containing the whole project folder contents
        createProjectZip($projectDir, $projectId);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        $requestUri = $_SERVER['REQUEST_URI'];
        $parsedUrl = parse_url($requestUri);
        $path = $parsedUrl['path'] ?? '/';
        
        $dir = str_replace('\\', '/', dirname($path));
        $dir = rtrim($dir, '/');
        
        $projectUrl = $protocol . $host . $dir . '/projects/' . rawurlencode($projectId) . '/index.html';
        $zipUrl = $protocol . $host . $dir . '/projects/' . rawurlencode($projectId) . '/' . rawurlencode($projectId) . '.zip';

        showSuccessPage($projectId, $projectUrl, $zipUrl);
    } catch (Throwable $e) {
        showErrorPage($e->getMessage());
    }
}

$action = $_GET['action'] ?? '';
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    handleSaveProject($storageRoot);
}

$dirExists = is_dir($storageRoot);
$dirWritable = $dirExists && is_writable($storageRoot);
if (!$dirExists) {
    $dirExists = @mkdir($storageRoot, 0775, true);
    $dirWritable = $dirExists && is_writable($storageRoot);
}
if (!$dirWritable) {
    showErrorPage("The projects directory is not writable.");
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ARTimate Builder</title>
  <script src="aframe_1.5.0.min.js"></script>
  <script src="mindar-image-aframe_1.2.5.prod.js"></script>
  <style>
    :root {
      color-scheme: light;
      font-family: system-ui, Arial, sans-serif;
    }
    * { box-sizing: border-box; }
    html, body {
      width: 100%;
      height: 100%;
      margin: 0;
      padding: 0;
    }
    body.builder-mode {
      margin: 24px;
      max-width: 960px;
    }
    .hidden { display: none !important; }
    .muted { opacity: 0.7; }
    .card {
      border: 1px solid #ddd;
      border-radius: 12px;
      padding: 16px;
    }
    .toolbar {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
    }
    .row {
      display: grid;
      grid-template-columns: 1fr 1fr auto;
      gap: 12px;
      align-items: center;
      margin-bottom: 10px;
    }
    .row label {
      display: block;
      font-size: 12px;
      opacity: 0.8;
      margin-bottom: 4px;
    }
    button {
      padding: 10px 14px;
      border-radius: 10px;
      border: 1px solid #ccc;
      background: #fafafa;
      cursor: pointer;
    }
    button.primary {
      background: #111;
      color: #fff;
      border-color: #111;
    }
    button:disabled { opacity: 0.5; cursor: not-allowed; }
    input[type="file"] { width: 100%; }
  </style>
</head>
<body class="builder-mode">

  <main id="builderApp">
    <h1>MindAR Image→Video Builder</h1>
    <p class="muted">Pick image+video pairs and generate a server-stored AR project link.</p>

    <div style="background: #e6f4ea; color: #137333; border: 1px solid #c4eed0; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.5;">
      <strong>✓ Server Check Succeeded:</strong><br/>
      • PHP Version: <?php echo phpversion(); ?><br/>
      • Storage folder: <code>projects/</code> (Exists & Writable)<br/>
      • Upload max size: <?php echo ini_get('upload_max_filesize'); ?> / Post max size: <?php echo ini_get('post_max_size'); ?>
    </div>

    <form id="builderForm" method="POST" action="index.php?action=save" enctype="multipart/form-data">
      <input type="hidden" name="targetsMindBase64" id="targetsMindBase64" />
      <input type="hidden" name="pairs" id="pairsMetadataInput" />

      <div class="card">
        <div class="toolbar">
          <button type="button" id="addPairBtn">+ Add pair</button>
          <button type="submit" class="primary" id="generateBtn">Generate</button>
          <span id="status" class="muted"></span>
        </div>
        <hr style="margin:16px 0; border:none; border-top:1px solid #eee;" />
        <div id="pairs"></div>
      </div>
    </form>
  </main>

  <script>
    const pairsEl = document.getElementById("pairs");
    const statusEl = document.getElementById("status");
    const generateBtn = document.getElementById("generateBtn");
    const builderForm = document.getElementById("builderForm");
    let pairCount = 0;

    function setStatus(msg, cls = "muted") {
      if (!statusEl) return;
      statusEl.className = cls;
      statusEl.textContent = msg;
    }

    function toImageElement(file) {
      return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error(`Failed to load image`)); };
        img.src = url;
      });
    }

    function addPairRow() {
      pairCount += 1;
      const idx = pairCount;
      const wrap = document.createElement("div");
      wrap.className = "row";
      wrap.innerHTML = `
        <div>
          <label>Image ${idx} (target)</label>
          <input type="file" accept="image/*" data-kind="image" required />
        </div>
        <div>
          <label>Video ${idx}</label>
          <input type="file" accept="video/*" data-kind="video" required />
        </div>
        <div>
          <button type="button" data-action="remove">Remove</button>
        </div>
      `;
      wrap.querySelector('[data-action="remove"]').onclick = () => wrap.remove();
      pairsEl.appendChild(wrap);
    }

    function getSelectedPairs() {
      const rows = [...pairsEl.querySelectorAll(".row")];
      const pairs = [];
      for (const row of rows) {
        const imageFile = row.querySelector('input[data-kind="image"]').files[0];
        const videoFile = row.querySelector('input[data-kind="video"]').files[0];
        if (!imageFile || !videoFile) throw new Error("Missing files.");
        pairs.push({ imageFile, videoFile });
      }
      if (pairs.length === 0) throw new Error("Add at least one pair.");
      return pairs;
    }

    builderForm.onsubmit = async function(e) {
      e.preventDefault();
      try {
        generateBtn.disabled = true;
        setStatus("Preparing files...", "muted");

        const selected = getSelectedPairs();
        const imageElements = [];
        for (let i = 0; i < selected.length; i += 1) {
          imageElements.push(await toImageElement(selected[i].imageFile));
        }

        const compiler = new window.MINDAR.IMAGE.Compiler();
        await compiler.compileImageTargets(imageElements, (percent) => {
          setStatus(`Compiling... ${Math.round(percent)}%`, "muted");
        });
        
        const compiled = compiler.exportData();
        const targetsBuffer = compiled.buffer.slice(compiled.byteOffset, compiled.byteOffset + compiled.byteLength);

        const reader = new FileReader();
        reader.onload = function() {
          document.getElementById("targetsMindBase64").value = reader.result.split(',')[1];
          document.getElementById("pairsMetadataInput").value = JSON.stringify(selected.map((p, i) => ({
            imageName: p.imageFile.name, 
            videoName: p.videoFile.name, 
            videoType: p.videoFile.type || "video/mp4",
            height: imageElements[i].naturalHeight,
            width: imageElements[i].naturalWidth
          })));

          [...pairsEl.querySelectorAll(".row")].forEach((row, index) => {
            row.querySelector('input[data-kind="image"]').name = `image_${index}`;
            row.querySelector('input[data-kind="video"]').name = `video_${index}`;
          });
          builderForm.submit();
        };
        reader.readAsDataURL(new Blob([targetsBuffer]));
      } catch (err) {
        setStatus(err.message, "err");
        generateBtn.disabled = false;
      }
    };

    document.getElementById("addPairBtn").onclick = addPairRow;
    addPairRow();
  </script>
</body>
</html>