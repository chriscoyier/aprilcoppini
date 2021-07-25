<?php http_response_code(503); ?>
<?php error_log("Flywheel::DatabaseConnection"); ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
	<head>
		<title>Database connectivity issue</title>

		<meta name="viewport" content="width=device-width, initial-scale = 1.0; maximum-scale=1.0, user-scalable=no" />
		<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
		<link href="//fonts.googleapis.com/css?family=Lato:400,700" rel="stylesheet" type="text/css">
		<style type='text/css'>
			html { -moz-osx-font-smoothing: grayscale; -webkit-font-smoothing: antialiased; }
			body { margin: 0; font-family: "Lato", Helvetica, Arial, sans-serif; min-width: 320px; }
			.layout { display: flex; width: 100%; height: 100vh; min-height: 400px; }
			.layout__content { display: flex; flex: 12; justify-content: center; align-items: center; padding-bottom: 12.5vh; }
			.kitchensink { max-width: 850px; width: 90%; }
			div { width: 600px; margin: 0 auto; text-align: center; color: #262727; }
			h1 { font-size: 42px; font-weight: 900; letter-spacing: 0.02em; margin-top: 0; margin-bottom: 12px; color: #EF4E65; }

			@media (max-width: 500px) {
				h1 { font-size: 32px; font-weight: 900; letter-spacing: 0.02em; margin-top: 0; margin-bottom: 12px; }
			}

			p { margin-top: 0; font-size: 18px; margin-bottom: 30px; line-height: 1.5; max-width: 36em; margin: 0 auto; }
			a, a:visited { color: #50C6DB; text-decoration: none; }
			a:hover, a:active, a:focus { color: #3B91A1; }
			.error-code { line-height: 20px; position: relative; border: 4px solid currentColor; color: #EF4E65; border-radius: 0.1em; padding: 0.5em; display: inline-block; font-size: 42px; font-weight: 900; margin-bottom: 20px; }
			.error-code::after { content: "!"; display: block; width: 32px; height: 32px; font-size: 18px; line-height: 32px; background: #EF4E65; color: white; font-weight: 900; border-radius: 100%; position: absolute; top: -20px; right: -20px; }
			hr { margin: 30px 0; border: 0; background: #e7e7e7; width: 100%; height: 1px; }
		</style>
	</head>

	<body>
		<div class="layout">
			<div class="layout__content">
				<div class="kitchensink text-center">
					<p class="error-code">503</p>
					<h1>There was a database connection issue</h1>
					<hr />
					<p>The server was unable to connect to the database. You might try refreshing the page. If you continue to get this message, you should contact the site&nbsp;owner.</p>
				</div>
			</div>
		</div>
		<!-- ECFWDB503 -->
	</body>
</html>
