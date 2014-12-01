<?php
	include('crawler.php');
	include('FocusedCrawler.php');
	// phpinfo();
	$topic = "Shows, eventos, lazer, festas na bahia";
	// echo stem_portuguese("apartamento");die();
	$crawler = new FocusedCrawler($topic);


	// $crawler = new Crawler($topic);

	// debugPrint($crawler->getBaseUrlByTopic());
	// $crawler->initializeWeightTable();
	// $relevantPages  = $crawler->getRelevantPages();
	// debugPrint($relevantPages);
?>