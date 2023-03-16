<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Nyholm\Psr7\UploadedFile;

require __DIR__ . '/../inc/config.php';
require __DIR__ . '/../model/UserModel.php';

require __DIR__ . '/../vendor/autoload.php';

/**
 * Instantiate App
 *
 * In order for the factory to work you need to ensure you have installed
 * a supported PSR-7 implementation of your choice e.g.: Slim PSR-7 and a supported
 * ServerRequest creator (included with Slim PSR-7)
 */
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

$app->options('/{routes:.+}', function ($request, $response, $args) {
  return $response;
});

$app->add(function ($req, $handler) {
  $response = $handler->handle($req);
  return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
});


$app->get('/users', function (Request $request, Response $response, $args) {
    
  //! "@" remuje errory z tego polecenia (zrobić to docelowo w prod)
  $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

  $query = "SELECT * FROM `Users`";
  $result = @mysqli_query($connection, $query);

  while ($row = $result->fetch_assoc()){
    $data[] = $row;
  }

  $response->getBody()->write(json_encode($data));

  return $response;
});

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
  
  try {
      $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);
      //! strip slashes ?
      $query = "SELECT * FROM Users WHERE id = '" . stripslashes($args['id']) . "'";
      $result = @mysqli_query($connection, $query);

      while ($row = $result->fetch_assoc()){
        $data[] = $row;
      }

      $data = $data[0];

      $response->getBody()->write(json_encode($data));
      return $response;
    } catch (Exception $e) {
      $response = $response->withStatus(500);
      $response->getBody()->write(json_encode($e));
      return $response;
    }
});

$app->post('/api/login', function (Request $request, Response $response, $args) {
  $data = $request->getParsedBody();
  $username = $data['username'];
  $password = $data['password'];

  try {
    $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

    $query = "SELECT * FROM Users WHERE username = '" . stripslashes($username) . "' AND password = '" . stripslashes($password) . "'";
    $result = @mysqli_query($connection, $query);

    while ($row = $result->fetch_assoc()){
      $data[] = $row;
    }
    
    if (empty($data)) {
      $response = $response->withStatus(401);
      $response->getBody()->write("{\"error\": \"Invalid username or password\"}");
      return $response;
    } else {
      $response = $response->withStatus(200);
      $response->getBody()->write(json_encode($data[0]));
      return $response;
    }
  } catch (Exception $e) {
    $response = $response->withStatus(500);
    $response->getBody()->write("{\"error\": \"" . $e->getMessage() . "\"}");
    return $response;
  }

});

$app->post('/api/signup' , function (Request $request, Response $response, $args) {
  $data = $request->getParsedBody();
  $username = $data['username'];
  $password = $data['password'];
  $email = $data['email'];
  $firstName = $data['firstName'];
  $lastName = $data['lastName'];

  try {
    $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

    $query = "SELECT * FROM Users WHERE username = '" . stripslashes($username) . "'";
    $result = @mysqli_query($connection, $query);

    while ($row = $result->fetch_assoc()){
      $data[] = $row;
    }
    
    if ($data[0]) {
      $response = $response->withStatus(400);
      $response->getBody()->write("{\"error\": \"Username already exists\"}");
      return $response;
    } else {
      //! stripslashes
      $query = "INSERT INTO Users (username, password, email, full_name) VALUES ('" . stripslashes($username) . "', '" . stripslashes($password) . "', '" . stripslashes($email) . "', '" . stripslashes($firstName) . " " . stripslashes($lastName) ."')";
      $result = @mysqli_query($connection, $query);

      $response = $response->withStatus(200);
      $response->getBody()->write("{\"message\": \"User created successfully\"}");
      return $response;
    }
  } catch (Exception $e) {
    $response = $response->withStatus(500);
    $response->getBody()->write("{\"error\": \"" . $e->getMessage() . "\"}");
    return $response;
  }

});

$app->get('/posts', function (Request $request, Response $response, $args) {
  $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

  $query = "SELECT p.id, p.title, p.body, p.link, p.imageName, p.authorId, p.created_at, GROUP_CONCAT(u.user_id) AS upvotes
  FROM Posts p
  LEFT JOIN UpVotes u ON p.id = u.post_id
  GROUP BY p.id";
  $result = @mysqli_query($connection, $query);

  while ($row = $result->fetch_assoc()){
    if ($row['upvotes'] == null) {
      $row['upvotes'] = [];
    } else {
      $row['upvotes'] = explode(',', $row['upvotes']);
    }
    $data[] = $row;
  }

  // get all the comments for each post
  foreach ($data as $key => $post) {
    // in query include the user object
    $query = "SELECT c.id, c.body, c.user_id, c.post_id, c.created_at, u.username, u.full_name, u.avatar
    FROM Comments c
    LEFT JOIN Users u ON c.user_id = u.id
    WHERE c.post_id = '" . $post['id'] . "'";
    $result = @mysqli_query($connection, $query);

    while ($row = $result->fetch_assoc()){
      $data[$key]['comments'][] = $row;

      // move full_name, username, avatar to user object
      $data[$key]['comments'][count($data[$key]['comments']) - 1]['user'] = [
        'id' => $data[$key]['comments'][count($data[$key]['comments']) - 1]['user_id'],
        'username' => $data[$key]['comments'][count($data[$key]['comments']) - 1]['username'],
        'full_name' => $data[$key]['comments'][count($data[$key]['comments']) - 1]['full_name'],
        'avatar' => $data[$key]['comments'][count($data[$key]['comments']) - 1]['avatar'],
      ];

      // remove full_name, username, avatar from comment object
      unset($data[$key]['comments'][count($data[$key]['comments']) - 1]['user_id']);
      unset($data[$key]['comments'][count($data[$key]['comments']) - 1]['username']);
      unset($data[$key]['comments'][count($data[$key]['comments']) - 1]['full_name']);
      unset($data[$key]['comments'][count($data[$key]['comments']) - 1]['avatar']);
    }

    // if the comments array is empty, return an empty array
    if (empty($data[$key]['comments'])) {
      $data[$key]['comments'] = [];
    }
  }

  $response = $response->withStatus(200);
  $response->getBody()->write(json_encode($data));

  return $response;
});

$app->get('/posts/{postId}', function (Request $request, Response $response, $args) {
  $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

  $query = "SELECT p.id, p.title, p.body, p.link, p.imageName, p.authorId, p.created_at, GROUP_CONCAT(u.user_id) AS upvotes
  FROM Posts p
  LEFT JOIN UpVotes u ON p.id = u.post_id
  WHERE p.id = '" . $args['postId'] . "'
  GROUP BY p.id";
  $result = @mysqli_query($connection, $query);

  while ($row = $result->fetch_assoc()){
    if ($row['upvotes'] == null) {
      $row['upvotes'] = [];
    } else {
      $row['upvotes'] = explode(',', $row['upvotes']);
    }
    $data[] = $row;
  }

  $data = $data[0];

  // if comments array is empty, return an empty array
  if (empty($data['comments'])) {
    $data['comments'] = [];
  }

  // get all the comments for that post
  $query = "SELECT c.id, c.body, c.user_id, c.post_id, c.created_at, u.username, u.full_name, u.avatar
  FROM Comments c
  LEFT JOIN Users u ON c.user_id = u.id
  WHERE c.post_id = '" . $args['postId'] . "'";
  $result = @mysqli_query($connection, $query);

  while ($row = $result->fetch_assoc()){
    $data['comments'][] = $row;

    // move full_name, username, avatar to user object
    $data['comments'][count($data['comments']) - 1]['user'] = [
      'id' => $data['comments'][count($data['comments']) - 1]['user_id'],
      'username' => $data['comments'][count($data['comments']) - 1]['username'],
      'full_name' => $data['comments'][count($data['comments']) - 1]['full_name'],
      'avatar' => $data['comments'][count($data['comments']) - 1]['avatar'],
    ];

    // remove full_name, username, avatar from comment object
    unset($data['comments'][count($data['comments']) - 1]['user_id']);
    unset($data['comments'][count($data['comments']) - 1]['username']);
    unset($data['comments'][count($data['comments']) - 1]['full_name']);
    unset($data['comments'][count($data['comments']) - 1]['avatar']);
  }

  $response = $response->withStatus(200);
  $response->getBody()->write(json_encode($data));

  return $response;
});

$app->post('/posts', function (Request $request, Response $response, $args) {
  $body = $request->getParsedBody();
  // type: varchar
  $title = $body['title'];
  // type: text
  $content = $body['content'];
  // type: int
  $author = $body['author'];
  $link = $body['link'];
  $imageName = $body['imageName'];
  
  try {
    $connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

    if ($link != null || $link != "") {
      $query = "INSERT INTO Posts (title, authorId, link) VALUES ('" . $title . "', '" . stripslashes($author) . "', '" . stripslashes($link) . "')";
    } else if ($content != null || $content != "") {
      $query = "INSERT INTO Posts (title, body, authorId) VALUES ('" . $title . "', '" . $content . "', '" . stripslashes($author) . "')";
    } else if ($imageName != null || $imageName != "") {
      $query = "INSERT INTO Posts (title, authorId, imageName) VALUES ('" . $title . "', '" . stripslashes($author) . "', '" . $imageName . "')";
    } else {
      $response = $response->withStatus(400);
      $response->getBody()->write("{\"error\": \"Invalid request\"}");
      return $response;
    }
    $result = mysqli_query($connection, $query);

    $response = $response->withStatus(200);
    $response->getBody()->write("{\"message\": \"Post created successfully\"}");
    return $response;
  } catch (Exception $e) {
    $response = $response->withStatus(500);
    $response->getBody()->write("{\"error\": \"" . $e->getMessage() . "\"}");
    return $response;
  }

});

$app->put('/upvotes/{id}', function (Request $request, Response $response, $args) {
  $body = $request->getParsedBody();
  $id = $args['id'];
  $userId = $body['userId'];
  $operation = $body['operation'];

  $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

  switch ($operation) {
    case 'add':
      $query = "INSERT INTO UpVotes (post_id, user_id) VALUES ('" . stripslashes($id) . "', '" . stripslashes($userId) . "')";
      $result = @mysqli_query($connection, $query);

      $response = $response->withStatus(200);
      $response->getBody()->write("{\"message\": \"Upvote added successfully\"}");
      break;
    case 'remove':
      $query = "DELETE FROM UpVotes WHERE post_id = '" . stripslashes($id) . "' AND user_id = '" . stripslashes($userId) . "'";
      $result = @mysqli_query($connection, $query);

      $response = $response->withStatus(200);
      $response->getBody()->write("{\"message\": \"Upvote removed successfully\"}");
      break;
    default:
      $response = $response->withStatus(400);
      $response->getBody()->write("{\"error\": \"Invalid operation\"}");
      return $response;
      break;
  }

  $response = $response->withStatus(200);
  return $response;
});

$app->put('/posts/{id}', function (Request $request, Response $response, $args) {
  $body = $request->getParsedBody();
  $id = $args['id'];
  $userId = $body['userId'];

  $connection = @mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

  $connection->begin_transaction();

  $query = "DELETE FROM UpVotes WHERE post_id = " . stripslashes((int)$id);
  $result = @mysqli_query($connection, $query);

  if(!$result) {
    $connection->rollback();
    $response = $response->withStatus(500);
    $response->getBody()->write("{\"error\": \"Error deleting upvotes\"}");
    return $response;
  }

  $query = "DELETE FROM Posts WHERE id = " . stripslashes((int)$id);
  $result = @mysqli_query($connection, $query);

  if(!$result) {
    $connection->rollback();
    $response = $response->withStatus(500);
    $response->getBody()->write("{\"error\": \"Error deleting post\"}");
    return $response;
  }

  $connection->commit();

  $response = $response->withStatus(200);
  $response->getBody()->write("{\"message\": \"Post deleted successfully\"}");
  return $response;
});

$app->post('/upload/{path}', function (Request $request, Response $response, $args) {
  $uploadedFile = $request->getUploadedFiles()['file'];
  $path = $args['path'];
  
  if ($path == null || $path == "") {
    $response = $response->withStatus(400);
    $response->getBody()->write("{\"error\": \"Invalid path variable.\"}");
    return $response;
  }
  
  if ($uploadedFile instanceof UploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
      $directory = __DIR__ . '/../../frontend/public/' . $path;
      $filename = moveUploadedFile($directory, $uploadedFile);
      $response = $response->withStatus(200);
      $response->getBody()->write("{\"message\": \"File uploaded successfully\", \"filename\": \"" . stripslashes($filename) . "\"}");
      return $response;
  }
  
  $response = $response->withStatus(500);
  $response->getBody()->write("{\"error\": \"Error uploading file\"}");
});

$app->post('/comments', function (Request $request, Response $response, $args) {
  $body = $request->getParsedBody();
  // type: varchar
  $content = $body['content'];
  // type: int
  $author = $body['author'];
  $postId = $body['postId'];
  
  try {
    $connection = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DATABASE_NAME);

    $query = "INSERT INTO Comments (body, user_id, post_id) VALUES ('" . $content . "', '" . stripslashes($author) . "', '" . stripslashes($postId) . "')";
    $result = mysqli_query($connection, $query);

    // get the comment where user_id = $author and post_id = $postId
    $query = "SELECT * FROM Comments WHERE user_id = '" . stripslashes($author) . "' AND post_id = '" . stripslashes($postId) . "'";
    $result = mysqli_query($connection, $query);
    $comment = mysqli_fetch_assoc($result);

    $response = $response->withStatus(200);
    $response->getBody()->write("{\"message\": \"Comment created successfully\", \"comment\": " . json_encode($comment) . "}");
    return $response;
  } catch (Exception $e) {
    $response = $response->withStatus(500);
    $response->getBody()->write("{\"error\": \"" . $e->getMessage() . "\"}");
    return $response;
  }

});

function moveUploadedFile($directory, UploadedFile $uploadedFile) {
  $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
  $basename = bin2hex(random_bytes(8));
  $filename = sprintf('%s.%0.8s', $basename, $extension);
  
  $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
  
  return $filename;
}


// Run app
$app->run();