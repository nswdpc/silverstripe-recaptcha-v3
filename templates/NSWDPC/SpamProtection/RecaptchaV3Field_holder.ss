$Field
<% if $Message %><span class="message $MessageType">$Message</span><% end_if %>

<% if $ShowRecaptchaV3Badge == 'form' %>
<% include NSWDPC/SpamProtection/FormBadge %>
<% end_if %>
