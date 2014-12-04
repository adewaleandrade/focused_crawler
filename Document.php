<?php

	/**
	 * Web Crawler Focado
	 * 
	 * @see https://github.com/adewaleandrade/focused_crawler
	 * @package    focused_crawler
	 * @author     Adewale Andrade D Alcantara
	 * @license    
	**/

	include_once('libs/kohana-pt-inflector/classes/inflector.php');
	include_once('FocusedCrawler.php');


	set_time_limit(0);

	class Document {

		public $terms;
		public $stemmedFrequencies;
		public $keyTermsWeights;
		public $url;
		public $pageUrls;
		public $crawler;
		private $weightTable;
		public $irrelevantDrillLevel;

		/**
		 * Constructor
		 *
		 * @param   string url	
		 * @param   FocusedCrawler crawler	
		 * @param   int isSeed		 
		 */
		function Document($url, FocusedCrawler &$crawler, WeightTable &$weightTable, $irrelevantDrillLevel = 0, $isSeed = 0 ){
			$this->url = $url;
			$this->terms = array();
			$this->stemmedDictionary = array();
			$this->keyTermsWeights = array();
			$this->pageUrls = array();
			$this->crawler = $crawler;
			$this->weightTable = $weightTable;
			$this->irrelevantDrillLevel = $irrelevantDrillLevel;

			//Download and Parse page
			$page = file_get_html2($url);

			if($page){
				if($isSeed){
					$this->terms = $this->sanitizePageContents($page);
					$this->getTermFrequencies();

				}else{
					$pageTitle = $page->find('title');
					$this->getSectionWeights($pageTitle, 2);

					$pageBody = $page->find('body');
					$this->getSectionWeights($pageBody);

					//Pega os links dentro da página
					$pageLinks = $page->find('a');
					$this->rankPageLinks($pageLinks);
				}

				$this->weightTable->documentCount += 1;
				// $this->weightTable->updateDictionary($this->stemmedDictionary);
			} else return false;
		}

		function sanitizePageContents($page){
			$text = $page->plaintext;
			// $text =  utf8_decode(strip_tags(htmlentities($page->plaintext,null,"UTF-8")));
			// $text = htmlspecialchars($page->plaintext, ENT_QUOTES, "UTF-8");

			$stopWords = array(
				'de', 'a', 'o', 'que', 'e', 'do', 'da', 'em', 'um', 'para', 'é', '&eacute;', 'com','não', 'n&atilde;o', 'uma', 'os', 'no',
				'se', 'na', 'por', 'mais', 'as', 'dos', 'como', 'mas', 'foi', 'ao', 'ele', 'das', 'tem', 'à', '&agrave;', 'seu',
				'sua', 'ou', 'ser', 'quando', 'muito', 'há', 'h&aacute;', 'nos', 'já','j&aacute;', 'está', 'est&aacute;', 'eu', 'também', 'tamb&eacute;m', 'só', 's&oacute;', 'pelo', 'pela', 'até', 
				'isso', 'ela', 'entre', 'era', 'depois', 'sem', 'mesmo', 'aos', 'ter', 'seus', 'quem', 'nas', 'me', 'esse', 'eles', 'estão', 'est&atilde;o',
				'você', 'voc&ecirc;', 'tinha', 'foram', 'essa', 'num', 'nem', 'suas', 'meu', 'às', '&agrave;s', 'minha', 'têm', 't&ecirc;m', 'numa', 'pelos', 'elas', 'havia', 'seja',
				'qual', 'será', 'ser&aacute;', 'nós', 'n&oacute;s', 'tenho', 'lhe', 'deles', 'essas', 'esses', 'pelas', 'este', 'fosse', 'dele', 'tu', 'te', 'vocês', 'voc&ecirc;s', 'vos', 'lhes',
				'meus', 'minhas', 'teu', 'tua', 'teus', 'tuas', 'nosso', 'nossa', 'nossos', 'nossas', 'dela', 'delas', 'esta', 'estes', 'estas', 'aquele',
				'aquela', 'aqueles', 'aquelas', 'isto', 'aquilo', 'estou', 'está', 'estamos', 'estão', 'estive', 'esteve', 'estivemos', 'estiveram',
				'estava', 'estávamos', 'est&aacute;vamos', 'estavam', 'estivera', 'estivéramos', 'estiv&eacute;ramos', 'esteja', 'estejamos', 'estejam', 'estivesse', 'estivéssemos', 'estiv&eacute;ssemos', 'estivessem',
				'estiver', 'estivermos', 'estiverem', 'hei', 'há', 'h&aacute;', 'havemos', 'hão', 'h&atilde;o', 'houve', 'houvemos', 'houveram', 'houvera', 'houvéramos', 'houv&eacute;ramos', 
				'haja', 'hajamos', 'hajam', 'houvesse', 'houvéssemos', 'houv&eacute;ssemos', 'houvessem', 'houver', 'houvermos', 'houverem', 'houverei', 'houverá', 'houver&aacute;', 'houveremos',
				'houverão', 'houver&atilde;o', 'houveria', 'houveríamos', 'houver&iacute;amos', 'houveriam', 'sou', 'somos', 'são', 's&atilde;o', 'era', 'éramos', '&eacute;ramos', 'eram', 'fui', 'foi', 'fomos',
				'foram', 'fora', 'fôramos', 'seja', 'sejamos', 'sejam', 'fosse', 'fôssemos', 'f&ocirc;ssemos', 'fossem', 'for', 'formos', 'forem', 'serei',
				'será', 'ser&aacute;', 'seremos', 'serão', 'ser&atilde;o', 'seria', 'seríamos', 'ser&iacute;amos', 'seriam', 'tenho', 'tem', 'temos', 'tém', 't&eacute;m', 'tinha', 'tínhamos', 't&iacute;nhamos', 'tinham', 'tive',
				'teve', 'tivemos', 'tiveram', 'tivera', 'tivéramos', 'tiv&eacute;ramos', 'tenha', 'tenhamos', 'tenham', 'tivesse', 'tivéssemos', 'tiv&eacute;ssemos', 'tivessem', 'tiver', 
				'tivermos', 'tiverem', 'terei', 'terá', 'ter&aacute;', 'teremos', 'terão', 'ter&atilde;o', 'teria', 'teríamos', 'ter&iacute;amos', 'teriam', 
				'ba', 'nov', 'dez', 'dia', 'ax','possui', 'possuem', 'outros', 'outro', 'todo', 'todos', 'toda', 'todas', 'onde',
				'&nbsp;', 'ã©', 'eacute', 'agraves', 'r', '&bull;', 'moi', '©', '&;'
			);

			// //Remove caracteres especiais e numeros
			$text = preg_replace('/[0-9]h/s', '', $text);
			$text = preg_replace('/[^a-zA-ZãÃáàâõóòôêéèíìúùç&;\- ]/s', '', $text);
			
			$text =strtolower($text);
			$bagOfWords = explode(' ', $text);

			// Remove palavras desnecessárias
			foreach ($bagOfWords as $k => $w) {
				if (in_array($w, $stopWords) || (strlen($w) <= 1)) {
					unset($bagOfWords[$k]);
				}
			}

			return array_values($bagOfWords);
		}


		function getTermFrequencies(){
			foreach ($this->terms as $term) {
				$this->computeFrequencies($term);
			}
		}

		function computeFrequencies ($term){
			$this->weightTable->termFrequencies[$term] = isset($this->weightTable->termFrequencies[$term])?($this->weightTable->termFrequencies[$term] + 1):1;
			// debugPrint($term .'=>'.$this->weightTable->termFrequencies[$term]);
			if(!isset($this->weightTable->tDocFrequencies[$term]['urls'][$this->url])){
				$this->weightTable->tDocFrequencies[$term]['urls'][$this->url] = $this->url;
				$this->weightTable->tDocFrequencies[$term]['count'] = isset($this->weightTable->tDocFrequencies[$term]['count'])?$this->weightTable->tDocFrequencies[$term]['count'] + 1:1;
			}
		}

		
		/**
		 * 
		 *
		 * @param   string   word to singularize
		 * @param   integer  count of thing
		 * @return  string
		 * @uses    Inflector::uncountable
		 */
		function getSectionWeights($sections, $baseScore = 1){
			foreach ($sections as $section) {
				$cleanSectionTerms = $this->sanitizePageContents($section);
				$stemmedKeyTerms = array_keys($this->weightTable->stemmedKeyWords);

				foreach ($cleanSectionTerms as $term) {
					$this->terms[] = $term;

					$stemmedDocTerm = stem_portuguese($term);
					// Get section weight for key words
					if(in_array($stemmedDocTerm, $stemmedKeyTerms)){
						$this->keyTermsWeights[$stemmedDocTerm] = isset($this->keyTermsWeights[$stemmedDocTerm])?($this->keyTermsWeights[$stemmedDocTerm] + $baseScore) : $baseScore;
					}
				}
			}
		}

		function rankPageLinks($links){
			foreach ($links as $link) {
				$normalizedUrl = normalizeUrl($this->url, $link->href);
				$linkScore = $this->getStringScore($normalizedUrl) + $this->getStringScore($link->plaintext) + $this->getRelevantReferenceScore($normalizedUrl);
				$this->pageUrls[$normalizedUrl] = $linkScore;
			}

			arsort($this->pageUrls);
		}

		function getStringScore($string){
			$score = 0;
			$string = preg_replace('/[^a-zA-ZãÃáàâõóòôêéèíìúùç&;\- ]/s', ' ', $string);
			$terms = explode(' ', $string);

			$keyTerms = array_keys($this->weightTable->stemmedKeyWords);
			foreach ($terms as $t) {
				if(in_array(stem_portuguese($t), $keyTerms)){
					$score += 1;
				}
			}

			return $score;
		}

		function getRelevantReferenceScore($url){
			$score=0;
			foreach ($this->crawler->relevantPages as $rpData) {
				if(in_array($url, $rpData['links'])){
					$score +=1;
				}
			}
			return $score;
		}

		function getPageUrls(){
			return $this->pageUrls;
		}

	}
?>