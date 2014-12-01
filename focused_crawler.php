<?php
	include('crawler.php');
	include('FocusedCrawler.php');

	$topic = "Shows, eventos, lazer, festas na bahia";
	echo stem_english('apartamento');die();
	$crawler = new FocusedCrawler($topic);


	// $crawler = new Crawler($topic);

	// debugPrint($crawler->getBaseUrlByTopic());
	// $crawler->initializeWeightTable();
	// $relevantPages  = $crawler->getRelevantPages();
	// debugPrint($relevantPages);
?>