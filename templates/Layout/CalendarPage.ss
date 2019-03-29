<div style="margin: 30px 50px">
<h1> Calendar</h1>
<p>
<a href="$Link">All Events</a> |
<a href="{$Link}day">Todays Events</a> |
<a href="{$Link}week">Events This Week</a> |
<a href="{$Link}month">Events This Month</a>
</p>
<hr/>
<% if $EventDateTimes %>
<% loop $EventDateTimes %>

    <a href="$Event.Link">
    <h4>$StartDate.nice</h4>
    <p>
        <small>$StartTime.nice <% if $EndTime %> - $EndTime.nice<% end_if %></small>
    </p>
    <p>id: $Event.ID</p>
    <h5>$Event.Title</h5>

    <% if not $AllDay == 0 %>
    <p>End Time: $AllDay </p>

    <% end_if %>

    </a>
    <% if not Last %>
    <hr/>
    <% end_if %>

<% end_loop %>

<% else %>

    <% if $SearchTerm %>
        <p>No events found for "$SearchTerm"</p>
    <% else %>
        <p>No events scheduled yet</p>

    <% end_if %>

<% end_if %>
</div>
