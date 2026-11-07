import urllib.request
resp = urllib.request.urlopen('http://localhost/ai-design-studio/index.php?lang=ar')
html = resp.read().decode('utf-8')
print('has modal in php output:', 'id="ai-poster-modal"' in html)
print('HTML length:', len(html))

