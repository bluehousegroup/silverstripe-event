<div style="margin: 30px 50px">
<h1> Calendar</h1>
<p>
<a href="calendar">All Events</a> |
<a href="calendar/day">Todays Events</a> |
<a href="calendar/week">Events This Week</a> |
<a href="calendar/month">Events This Month</a>
</p>
<hr/>
<h3>$Event.Title</h3>
<p></p>
<p>$Event.Description</p>

<% loop $Event.EventDateTimes %>
    <p>
        <small>$StartDate.nice @ $StartTime.nice <% if $EndTime %> - $EndTime.nice<% end_if %></small>
    </p>
<% end_loop %>
</div>