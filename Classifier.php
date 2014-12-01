<?php

	/**
	 * Web Crawler Focado
	 * 
	 * @see https://github.com/adewaleandrade/focused_crawler
	 * @package    focused_crawler
	 * @author     Adewale Andrade D Alcantara
	 * @license    
	**/

	include_once('FocusedCrawler.php');

	set_time_limit(0);

	class Classifier {
		private $crawler;

		/**
		 * Constructor
		 *
		 * @param   array userSettings		 
		 */

		function Classifier(FocusedCrawler $crawler){
			$this->crawler = $crawler;
		}

		/**
		 * Calculate Page Relevance
		 * @param   Document   	page on vector space model
		 * @return  integer 	similarity
		 */
		function calculatePageRelevance(Document $page){

			return $this->cosineSimilarity($page);
		}

		/**
		 * Calculate Page Similarity by Cosine
		 * @param   Document   	page on vector space model
		 * @return  integer 	similarity
		 */
		function cosineSimilarity(Document $page){

			$a = $b = $c = 0;
			foreach ($page->keyTermsWeights as $term => $w) {
				$a += $w * $this->crawler->weightTable->keyWords[$term];
				$b += $w * $w;
			}
			foreach ($this->crawler->weightTable->keyWords as $w) {
				$c += $w * $w;
			}

		    return $b * $c != 0 ? $a / sqrt($b * $c) : 0;
		}

	}
?>