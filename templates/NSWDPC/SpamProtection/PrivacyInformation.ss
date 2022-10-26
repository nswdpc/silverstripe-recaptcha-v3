<% if $DisplayOption == 'form' || $DisplayOption == 'field' %>
    <% include NSWDPC/SpamProtection/FormBadge %>
<% else_if $DisplayOption == 'page' %>
    <% include NSWDPC/SpamProtection/PageBadge %>
<% end_if %>
