<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index_administrationpage.php');
	
	class contentExtensionSearch_IndexQueries extends SearchIndex_AdministrationPage {
						
		public function view() {
			
			parent::view();
			
			// Set up page meta data
			/*-----------------------------------------------------------------------*/	
			
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes'));
			$this->appendSubheading(__('Search Index') . " &rsaquo; Log Analysis");
			
			
			// Get URL parameters, set defaults
			/*-----------------------------------------------------------------------*/	
			$sort = (object)$_GET['sort'];
			$filter = (object)$_GET['filter'];
			$pagination = (object)$_GET['pagination'];
			
			if (!isset($sort->column)) $sort->column = 'count';
			if (!isset($sort->direction)) $sort->direction = 'desc';
			if (!isset($filter->keywords)) $filter->keywords = '';
			if (!isset($filter->session_id)) $filter->session_id = '';
			if (!isset($filter->date_from)) $filter->date_from = date('Y-m-d', strtotime('last month'));
			if (!isset($filter->date_to)) $filter->date_to = date('Y-m-d', strtotime('today'));
			
			
			// Build pagination and fetch rows
			/*-----------------------------------------------------------------------*/
			$pagination->{'per-page'} = 5;
			$pagination->{'current-page'} = (@(int)$pagination->{'current-page'} > 1 ? (int)$pagination->{'current-page'} : 1);
			
			// get the logs!
			$rows = SearchIndexLogs::getQueries(
				$sort->column, $sort->direction,
				$pagination->{'current-page'}, $pagination->{'per-page'},
				$filter
			);
			
			// total number of unique query terms
			$pagination->{'total-entries'} = SearchIndexLogs::getTotalQueries($filter);
			
			$pagination->start = max(1, (($pagination->{'current-page'} - 1) * $pagination->{'per-page'}));
			$pagination->end = ($pagination->start == 1 ? $pagination->{'per-page'} : $pagination->start + count($rows));
			$pagination->{'total-pages'} = ceil($pagination->{'total-entries'} / $pagination->{'per-page'});
			
			// sum of the "count" column for all queries i.e. total number of searches
			$total_search_count = SearchIndexLogs::getSearchCount($filter);

			// cache amended filters for use elsewhere
			$this->sort = $sort;
			$this->filter = $filter;
			$this->pagination = $pagination;
			
			
			// Build table
			/*-----------------------------------------------------------------------*/
								
			$tableHead = array();
			$tableBody = array();
			
			// append table headings
			$tableHead[] = array(__('Rank'), 'col');
			$tableHead[] = $this->__buildColumnHeader(__('Query'), 'keywords', 'asc');
			$tableHead[] = $this->__buildColumnHeader(__('Count'), 'count', 'desc');
			$tableHead[] = array(__('%'), 'col');
			$tableHead[] = array(__('Cumulative %'), 'col');
			$tableHead[] = $this->__buildColumnHeader(__('Avg. results'), 'average_results', 'desc');
			$tableHead[] = $this->__buildColumnHeader(__('Avg. depth'), 'average_depth', 'desc');
			
			// no rows
			if (!is_array($rows) or empty($rows)) {
				$tableBody = array(
					Widget::TableRow(array(
						Widget::TableData(__('None Found.'), 'inactive', NULL, count($tableHead))
					))
				);
			}
			// we have rows
			else {
				
				// if not on the first page, the cululative percent column needs to start from the
				// column total of the previous page. Calling this method queries a dataset the size
				// of all previous pages, sums and returns the totals from all
				if($pagination->{'current-page'} > 1) {
					$cumulative_total = SearchIndexLogs::getCumulativeSearchCount(
						$sort->column, $sort->direction,
						$pagination->{'current-page'}, $pagination->{'per-page'},
						$filter
					);
				}
				
				// rank starts from 1 on first page
				$rank = ($pagination->start == 1) ? $pagination->start : $pagination->start + 1;
				// initial percentage to start from (cumulative)
				$cumulative_percent = ($cumulative_total / $total_search_count) * 100;
				
				foreach ($rows as $row) {
					
					$row_percent = ($row['count'] / $total_search_count) * 100;
					$cumulative_percent += $row_percent;
					
					$r = array();
					$r[] = Widget::TableData($rank, 'rank');
					$r[] = Widget::TableData(
						(empty($row['keywords']) ? __('None') : $row['keywords']),
						(empty($row['keywords']) ? 'inactive' : '')
					);
					$r[] = Widget::TableData($row['count'], 'count');
					$r[] = Widget::TableData((number_format($row_percent, 2)) . '%', 'percent');
					$r[] = Widget::TableData((number_format($cumulative_percent, 2)) . '%', 'percent');
					$r[] = Widget::TableData(number_format($row['average_results'], 1), 'average-results');
					$r[] = Widget::TableData(number_format($row['average_depth'], 1), 'average-depth');
					$tableBody[] = Widget::TableRow($r);
					
					$rank++;
					
				}
				
			}
			
			// append the table
			$table = Widget::Table(Widget::TableHead($tableHead), NULL, Widget::TableBody($tableBody));
			$this->Form->appendChild($table);
			
			// build pagination
			if ($pagination->{'total-pages'} > 1) {
				$this->Form->appendChild($this->__buildPagination($pagination));
			}

		}
		
	}