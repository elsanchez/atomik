<html>
	<head>
		<title>Atomik</title>
		<script type="text/javascript" src="scripts/jquery-1.2.6.min.js"></script>
	</head>
	<body>
	
		<?php foreach (Atomik_Session::getMessages() as $message): ?>
			<h2><?php echo $message ?></h2>
		<?php endforeach; ?>
	
		<?php echo $content_for_layout; ?>
	</body>
</html>
