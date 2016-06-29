<% if Tags %>
  <aside class="unit size1of4 secondary">
  		<h3>Tags</h3>
  		<ul>
  			<% loop Tags %>
  				<% if RelatedPages %>
  					<li><a href="$Link" title="View the $Title tag">$Title</a></li>
  				<% end_if %>
  			<% end_loop %>
  		</ul>
  </aside>
<% end_if %>
