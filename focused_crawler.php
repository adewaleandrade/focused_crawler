<?php
	include('crawler.php');

	$topic = "Shows e eventos na bahia";

	$crawler = new Crawler($topic);

	debugPrint($crawler->getBaseUrlByTopic());
	// $crawler->getRelevantPages();
	$crawler->initializeWeightTable();
?>