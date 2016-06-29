<% include EventSideBar %>
<div class="unit size3of4 lastUnit">
  <h1>$Title</h1>
  $Content

  <h4>$DateHeader</h4>
  <% if $Items %>
    <% loop $Items %>
      <section class="$EvenOdd clearfix">
        <% if DateAuthored %>$DateAuthored.Format(F j), $DateAuthored.Format(Y)<% end_if %>
      	<header>
      		<h4><a href="$Link">$Title</a></h4>
      	</header>
  			<p>
  				<strong>When:</strong> $Date.Format(F j Y)<% if $EndDate %> - $EndDate.Format(F j Y)<% end_if %><br>
  				<% if $Time %><strong>Time:</strong> $Time.Format(g:i a)<% if $EndTime %> - $EndTime.Format(g:i a)<% end_if %><br><% end_if %>
  			</p>

				$Content.FirstParagraph(html)
  			<% if Tags %>
  				<div class="half-bottom half-top">
  					<span class="tags"> Tags:&nbsp;&nbsp;
  						<% loop Tags %>
  							<a href="$Link" title="View all posts tagged '$Tag'" rel="tag">$Title</a><% if not Last %>&nbsp;&nbsp;|&nbsp;&nbsp;<% end_if %>
  						<% end_loop %>
  					</span>
  				</div>
  			<% end_if %>
  			<p class="add-top clearfix"><a href="$Link" class="readmore" title="Read Full Post">View the event</a></p>
      	<hr>
      </section>
    <% end_loop %>

    <% with $Items %>
      <% if MoreThanOnePage %>
      	<div class="apple_pagination clearfix">
      		<% if NotFirstPage %>
      			<a class="previous_page" href="$PrevLink" rel="previous">&lt; Previous</a></span>
      		<% else %>
      			<span class="disabled previous_page">&lt; Previous</span>
      		<% end_if %>

      		<% loop PaginationSummary(4) %>
      			<% if CurrentBool %>
      				<em class="current">$PageNum</em>
      			<% else %>
      				<% if Link %>
      					<a href="$Link">$PageNum</a>
      				<% else %>
      					<em>...</em>
      				<% end_if %>
      			<% end_if %>
      		<% end_loop %>
      		<% if NotLastPage %>
      			<a class="next_page" href="$NextLink" rel="next">Next &gt;</a>
      		<% else %>
      			<span class="disabled next_page">Next &gt;</span>
      		<% end_if %>
      	</div>
      <% end_if %>
		<% end_with %>
  <% else %>
    <p>There are no upcoming events. Check back soon!</p>
  <% end_if %>

</div>
