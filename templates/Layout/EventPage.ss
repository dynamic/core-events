<% include EventSideBar %>
<div class="unit size3of4 lastUnit">
    <article>
      <h1>$Parent.Title</h1>
  		<p><a href="$Parent.Link">&laquo; Back to $Parent.Title</a></p>
			<h2 class="summary">$Title</h2>

			<p>
				<strong>When:</strong> $Date.Format(F j Y)<% if $EndDate %> - $EndDate.Format(F j Y)<% end_if %><br>
				<% if $Time %><strong>Time:</strong> $Time.Format(g:i a)<% if $EndTime %> - $EndTime.Format(g:i a)<% end_if %><br><% end_if %>
			</p>

		  $Content

    </article>
	$Form
	$CommentsForm
</div>
